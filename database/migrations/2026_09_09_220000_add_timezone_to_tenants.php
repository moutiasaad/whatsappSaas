<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CALC-010: report day/hour buckets and reservation-dashboard counters
// were computed in UTC regardless of the tenant's real market. For a
// UTC+1 tenant this rotated the heat-map by one hour and mis-filed all
// traffic between 00:00 and 01:00 local time onto the previous day.
// Store a per-tenant IANA zone so aggregation can convert.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->string('timezone', 64)->default('UTC')->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropColumn('timezone');
        });
    }
};
