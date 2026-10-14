<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaksi_id',
        'payment_method',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_reference',
        'payment_url',
        'proof_path',
        'expires_at',
        'paid_at',
        'failure_reason',
        'metadata',
    ];

    protected $attributes = [
        'currency' => 'IDR',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksis::class, 'transaksi_id');
    }
}
