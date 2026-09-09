<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// UI-002: the plaintext otp_codes.code column undid the code_hash design.
// Anyone with read access to the row could read the live code and complete
// the phone-ownership check integrating tenants had built into their own
// flows. Verification only ever consulted code_hash, so removing the plaintext
// column is loss-less.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('otp_codes', 'code')) {
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->after('identifier');
        });
    }
};
