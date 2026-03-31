<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\BelongsTo;

class AccountWithdrawPix extends Model
{
    protected ?string $table = 'account_withdraw_pix';

    protected string $primaryKey = 'account_withdraw_id';

    public bool $incrementing = false;

    protected string $keyType = 'string';

    public bool $timestamps = false;

    protected array $fillable = [
        'account_withdraw_id',
        'type',
        'key',
    ];

    public function withdraw(): BelongsTo
    {
        return $this->belongsTo(AccountWithdraw::class, 'account_withdraw_id', 'id');
    }
}

