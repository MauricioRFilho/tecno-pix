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

        $accounts = [
            ['name' => 'Conta Demo Mauricio', 'balance' => '1500.00'],
            ['name' => 'Conta Demo Comercial', 'balance' => '2300.50'],
            ['name' => 'Conta Demo Operacional', 'balance' => '980.75'],
            ['name' => 'Conta Demo Reserva', 'balance' => '5000.00'],
        ];

        foreach ($accounts as $account) {
            Account::query()->create([
                'id' => Uuid::v4(),
                'name' => $account['name'],
                'balance' => $account['balance'],
            ]);
        }
    }
}
