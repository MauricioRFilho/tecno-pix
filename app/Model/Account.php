<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Swagger\Annotation as OA;

#[OA\Schema(
    schema: 'Account',
    required: ['id', 'name', 'balance'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '8b61f3e5-85c6-4e8f-9e1d-9c13f5eaf001'),
        new OA\Property(property: 'name', type: 'string', example: 'Conta Demo'),
        new OA\Property(property: 'balance', type: 'string', example: '1000.00'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class Account extends Model
{
    protected ?string $table = 'account';

    protected string $primaryKey = 'id';

    public bool $incrementing = false;

    protected string $keyType = 'string';

    protected array $fillable = [
        'id',
        'name',
        'balance',
    ];

    protected array $casts = [
        'balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function withdraws(): HasMany
    {
        return $this->hasMany(AccountWithdraw::class, 'account_id', 'id');
    }
}
