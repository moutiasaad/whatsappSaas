<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport;

class MailTest extends Command
{
    protected $signature = 'mail:test
                            {to? : Address to send the test message to}
                            {--connect-only : Only open + authenticate the SMTP session, do not send}';

    protected $description = 'Diagnose the configured mailer: port reachability, SMTP auth, then an optional live send';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $cfg    = config("mail.mailers.$mailer", []);

        $this->line('');
        $this->info("Mailer ......... {$mailer}");

        if (($cfg['transport'] ?? null) !== 'smtp') {
            $this->warn("Transport '{$cfg['transport']}' is not SMTP - skipping socket checks.");
            return $this->send($this->argument('to'));
        }

        $host = $cfg['host'] ?? '';
        $port = (int) ($cfg['port'] ?? 0);
        $enc  = $cfg['encryption'] ?? '';
        $user = $cfg['username'] ?? '';

        $this->info("Host ........... {$host}:{$port} (".($enc ?: 'none').')');
        $this->info('Username ....... '.($user !== '' ? $user : '(empty)'));
        $this->info('Password ....... '.(($cfg['password'] ?? '') !== '' ? 'set' : 'EMPTY'));
        $this->info('From ........... '.config('mail.from.address'));
        $this->line('');

        if (($cfg['password'] ?? '') === '') {
            $this->error('MAIL_PASSWORD is empty. The server will answer "501 5.5.4 Syntax: AUTH mechanism".');
            return self::FAILURE;
        }

        // 1. Raw TCP reachability - separates a provider port block from an auth problem.
        $this->comment('[1/3] TCP connect...');
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen(($enc === 'ssl' ? 'ssl://' : '').$host, $port, $errno, $errstr, 10);

        if (! $sock) {
            $this->error("      unreachable: {$errstr}");
            $this->line('');
            $this->warn('      A timeout here means outbound SMTP is blocked by the host/provider,');
            $this->warn('      not a credential problem. Ports 25/465/587 are commonly blocked;');
            $this->warn('      port 2525 usually is not.');
            return self::FAILURE;
        }

        $this->info('      reachable: '.trim((string) fgets($sock, 512)));
        fclose($sock);

        // 2. Real SMTP handshake + AUTH.
        $this->comment('[2/3] SMTP authentication...');

        try {
            $scheme = $enc === 'ssl' ? 'smtps' : 'smtp';
            $dsn = sprintf(
                '%s://%s:%s@%s:%d',
                $scheme,
                rawurlencode((string) $user),
                rawurlencode((string) $cfg['password']),
                $host,
                $port
            );

            $transport = Transport::fromDsn($dsn);
            $transport->start();
            $transport->stop();

            $this->info('      authenticated OK');
        } catch (\Throwable $e) {
            $this->error('      '.$e->getMessage());
            return self::FAILURE;
        }

        return $this->send($this->argument('to'));
    }

    private function send(?string $to): int
    {
        if ($this->option('connect-only') || ! $to) {
            $this->line('');
            $this->info($to ? 'Connection verified (no message sent).' : 'Connection verified. Pass an address to send a real test message.');
            return self::SUCCESS;
        }

        $this->comment("[3/3] Sending test message to {$to}...");

        try {
            Mail::raw(
                "Wavadesk SMTP test.\n\nIf you are reading this, outgoing mail works.\nSent at ".now()->toDateTimeString(),
                fn ($m) => $m->to($to)->subject('Wavadesk SMTP test')
            );
        } catch (\Throwable $e) {
            $this->error('      '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('      accepted by the server');
        $this->line('');
        $this->info("Delivered to the relay. Check {$to} (including spam).");

        return self::SUCCESS;
    }
}
