<?php

namespace App\Console\Commands;

use App\Models\AiSettings;
use Illuminate\Console\Command;

class RolloverAiQuotas extends Command
{
    protected $signature   = 'ai:rollover-quotas';
    protected $description = 'Reset expired AI monthly token quotas for idle tenants';

    public function handle(): int
    {
        $rolled  = 0;
        $skipped = 0;

        AiSettings::whereNotNull('quota_reset_at')
            ->where('quota_reset_at', '<=', now())
            ->cursor()
            ->each(function (AiSettings $settings) use (&$rolled, &$skipped) {
                $before = $settings->quota_reset_at;

                $settings->hasQuota();   // triggers rolloverIfDue()

                // hasQuota() short-circuits when the quota is NULL (unlimited)
                // or 0 (AI off), so only count rows whose period actually moved.
                $settings->quota_reset_at != $before ? $rolled++ : $skipped++;
            });

        $this->info("Rolled over {$rolled} AI quota row(s).");

        if ($skipped) {
            $this->line("Skipped {$skipped} row(s) still past due (unlimited or off — hasQuota() short-circuits).");
        }

        return self::SUCCESS;
    }
}
