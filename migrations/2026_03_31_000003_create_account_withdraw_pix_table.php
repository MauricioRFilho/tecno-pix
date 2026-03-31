<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_withdraw_pix', function (Blueprint $table) {
            $table->char('account_withdraw_id', 36)->primary();
            $table->string('type', 50);
            $table->string('key', 255);

            $table->foreign('account_withdraw_id')
                ->references('id')
                ->on('account_withdraw')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_withdraw_pix');
    }
};

