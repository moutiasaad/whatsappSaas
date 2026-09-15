<?php

namespace Tests\Support;

/**
 * config/wavadesk.php reads WAVADESK_ROLE through env() while the container
 * boots, and routes/web.php + routes/api.php branch on it as they are loaded.
 * By the time a test body runs, both have already happened — so the role has
 * to be in the environment *before* parent::setUp() creates the application,
 * which is what this trait exists to do.
 *
 * All three superglobals are written because Laravel's env repository reads
 * through putenv, $_ENV and $_SERVER adapters, and which one wins depends on
 * how the process was started (phpunit.xml vs. a real .env).
 */
trait SwitchesWavadeskRole
{
    /** A 64-char key, the shape `php -r "echo bin2hex(random_bytes(32));"` produces. */
    protected const SHARED_SECRET = '8f14e45fceea167a5a36dedd4bea2543c1bfc2d1e6b4ca2f0c2d1e6b4ca2f0c2';

    /** @var list<string> */
    private array $wavadeskEnvKeys = [];

    protected function presetWavadeskEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;

            $this->wavadeskEnvKeys[] = $key;
        }
    }

    /**
     * Leaving these set would leak the role into whichever test class PHPUnit
     * instantiates next, and a stray WAVADESK_ROLE=marketing makes unrelated
     * auth tests fail in a way that points nowhere near this file.
     */
    protected function clearWavadeskEnv(): void
    {
        foreach ($this->wavadeskEnvKeys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        $this->wavadeskEnvKeys = [];
    }
}
