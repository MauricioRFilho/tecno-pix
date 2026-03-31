<?php

declare(strict_types=1);

use App\Model\Account;
use App\Support\Uuid;
use Hyperf\Database\Seeders\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Account::query()->exists()) {
            return;
        }

        Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta Demo Tecno Pix',
            'balance' => '1000.00',
        ]);
    }
}
