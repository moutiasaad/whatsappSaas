<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Countries super-admin can add to enable per-country pricing.
 *
 * `code` is the ISO 3166-1 alpha-2 code and matches what Cloudflare's
 * CF-IPCountry header sends, so DetectCountry middleware can join on
 * this column directly without translation. `currency_code` is the ISO
 * 4217 code (USD/EUR/SAR/…) — same shape Stripe and PayPal expect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();          // e.g. 'SA'
            $table->string('name', 100);                  // 'Saudi Arabia'
            $table->string('currency_code', 3);           // 'SAR'
            $table->string('currency_symbol', 8);         // 'ر.س' / '$' / '€'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
