<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// as regras do domínio também no banco: o q entrar por fora (seed, tinker, script) não fura o tenant
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'name']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['company_id', 'id']);
            $table->dropForeign(['client_id']);
            // FK composta: a fatura só aponta pra cliente da mesma empresa. NO ACTION (e não RESTRICT)
            // pra checar no fim do comando, senão apagar a empresa inteira em cascata falha
            $table->foreign(['company_id', 'client_id'])->references(['company_id', 'id'])->on('clients')->noActionOnDelete();
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'due_date', 'id']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign(['company_id', 'invoice_id'])->references(['company_id', 'id'])->on('invoices')->cascadeOnDelete();
            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'id']);
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amount_positive CHECK (amount_cents > 0)');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_valid CHECK (status IN ('draft', 'open', 'paid', 'overdue'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_paid_at_matches_status CHECK ((status = 'paid') = (paid_at IS NOT NULL))");
        DB::statement("ALTER TABLE reminders ADD CONSTRAINT reminders_outcome_valid CHECK (outcome IN ('sent', 'already_sent', 'retry_later', 'rejected'))");
        // backstop do envio duplicado: no máximo um sent por chave. already_sent e retry podem repetir
        DB::statement("CREATE UNIQUE INDEX reminders_sent_key_unique ON reminders (idempotency_key) WHERE outcome = 'sent'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX reminders_sent_key_unique');
        DB::statement('ALTER TABLE reminders DROP CONSTRAINT reminders_outcome_valid');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_paid_at_matches_status');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_status_valid');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_amount_positive');

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'id']);
            $table->dropIndex(['company_id', 'invoice_id']);
            $table->dropForeign(['company_id', 'invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'due_date', 'id']);
            $table->dropIndex(['company_id', 'client_id']);
            $table->dropForeign(['company_id', 'client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
            $table->dropUnique(['company_id', 'id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'name']);
            $table->dropUnique(['company_id', 'id']);
            $table->index('company_id');
        });
    }
};
