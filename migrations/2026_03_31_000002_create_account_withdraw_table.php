<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_withdraw', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('account_id', 36);
            $table->string('method', 50);
            $table->decimal('amount', 15, 2);
            $table->boolean('scheduled')->default(false);
            $table->dateTime('scheduled_for')->nullable();
            $table->boolean('done')->default(false);
            $table->boolean('error')->default(false);
            $table->text('error_reason')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('account')->cascadeOnDelete();
            $table->index(['scheduled', 'done', 'error', 'scheduled_for'], 'idx_withdraw_scheduled');
            $table->index(['account_id'], 'idx_withdraw_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_withdraw');
    }
};

