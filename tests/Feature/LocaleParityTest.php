<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UI-004: guard against locale drift. Every shipped locale must expose the
 * same set of keys — the AI-settings page in Arabic was silently broken for
 * two audit cycles because 200 new en/fr keys never made it into ar. This
 * flattens each locale directory and asserts the key sets are identical.
 */
class LocaleParityTest extends TestCase
{
    private const LOCALES = ['en', 'fr', 'ar'];
    private const FILES   = ['ui', 'auth', 'landing', 'otp', 'validation'];

    public function test_all_locales_expose_the_same_key_set(): void
    {
        $sets = [];
        foreach (self::LOCALES as $loc) {
            $sets[$loc] = [];
            foreach (self::FILES as $file) {
                $path = base_path("lang/{$loc}/{$file}.php");
                if (!is_file($path)) {
                    continue;
                }
                $data = require $path;
                if (is_array($data)) {
                    $sets[$loc] = $sets[$loc] + $this->flatten($data, $file);
                }
            }
        }

        $union   = array_merge(...array_values($sets));
        $missing = [];
        foreach (self::LOCALES as $loc) {
            $delta = array_diff_key($union, $sets[$loc]);
            if ($delta) {
                $missing[$loc] = array_keys($delta);
            }
        }

        $this->assertEmpty(
            $missing,
            "Locale parity broken.\n" . $this->formatMissing($missing),
        );
    }

    private function flatten(array $arr, string $prefix): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $full = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $this->flatten($v, $full);
            } else {
                $out[$full] = true;
            }
        }
        return $out;
    }

    private function formatMissing(array $missing): string
    {
        $lines = [];
        foreach ($missing as $loc => $keys) {
            $lines[] = "  {$loc} is missing " . count($keys) . " keys:";
            foreach ($keys as $k) {
                $lines[] = "    - {$k}";
            }
        }
        return implode("\n", $lines);
    }
}
