<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CALC-005: tenant_payments.amount inherited decimal(10,3) from the original
// TND (three-decimal) integration. The June 2026 Stripe move changed only the
// currency default. The model already casts amount => decimal:2, so every
// write today rounds to two — but the schema still permits a third decimal
// that no currency in use can express. A direct DB edit, an import, or a
// future integration could stamp a value at a precision the gateway cannot
// charge and that would not reconcile against the payments list.
//
// Narrow the column to decimal(10,2), matching plans.price_monthly. Guarded:
// if any existing row carries a non-zero third decimal, this migration
// refuses to run so an operator investigates before precision is lost.
return new class extends Migration {
    public function up(): void
    {
        $lossyRows = DB::table('tenant_payments')
            ->whereRaw('ROUND(amount, 3) != ROUND(amount, 2)')
            ->count();

        if ($lossyRows > 0) {
            throw new \RuntimeException(
                "CALC-005: refusing to narrow tenant_payments.amount to decimal(10,2) — "
                . "{$lossyRows} row(s) have a non-zero third decimal. Investigate "
                . "before re-running: SELECT id, amount FROM tenant_payments "
                . "WHERE ROUND(amount, 3) != ROUND(amount, 2);"
            );
        }

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 3)->default(0)->change();
        });
    }
};
