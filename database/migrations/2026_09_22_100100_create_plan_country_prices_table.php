<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country monthly + annual prices for a plan. Missing (plan, country)
 * pair means the plan's base USD price applies for that country — no row
 * required just to say "we sell in USD here".
 *
 * FK to countries by CODE (not id) so the join stays readable and matches
 * the DetectCountry middleware, which resolves to codes not primary keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_country_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_annual', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'country_code']);
            $table->foreign('country_code')
                ->references('code')->on('countries')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_country_prices');
    }
};
