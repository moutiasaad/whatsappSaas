<?php

declare(strict_types=1);

/*
 * GitHub push -> production deploy.
 *
 * This file is internet-facing, so it deliberately does no shell execution and
 * no git work of its own. It verifies GitHub's HMAC signature and, if the push
 * targets the deploy branch, touches a trigger file. deploy/deploy-watcher.sh
 * (run by a root systemd timer) is what actually pulls and deploys.
 */

const DEPLOY_BRANCH = 'main';

$root = dirname(__DIR__);

function reply(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message, "\n";
    exit;
}

function envValue(string $path, string $key): ?string
{
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!str_starts_with($line, $key . '=')) {
            continue;
        }
        return trim(explode('=', $line, 2)[1], " \t\"'");
    }

    return null;
}

$secret = envValue($root . '/.env', 'GITHUB_WEBHOOK_SECRET');

if (!$secret) {
    reply(500, 'deploy webhook not configured');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, 'POST only');
}

$payload   = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected  = 'sha256=' . hash_hmac('sha256', $payload, $secret);

// hash_equals: constant time, so a wrong secret leaks nothing through timing.
if ($signature === '' || !hash_equals($expected, $signature)) {
    reply(403, 'bad signature');
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if ($event === 'ping') {
    reply(200, 'pong');
}

if ($event !== 'push') {
    reply(202, 'ignored event: ' . preg_replace('/[^a-z_]/', '', $event));
}

$ref = json_decode($payload, true)['ref'] ?? '';

if ($ref !== 'refs/heads/' . DEPLOY_BRANCH) {
    reply(202, 'ignored ref');
}

// The watcher polls for this file. Content is informational only — nothing
// downstream parses it, so a hostile payload cannot influence the deploy.
$trigger = $root . '/storage/framework/deploy.trigger';

if (@file_put_contents($trigger, gmdate('c') . "\n") === false) {
    reply(500, 'could not write deploy trigger');
}

reply(200, 'deploy queued');
