<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaksis extends Model
{
    protected $table = 'transaksis';

    protected $fillable = ['user_id', 'total_harga', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(TransaksisDetails::class, 'transaksi_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'transaksi_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'transaksi_id')->latestOfMany();
    }
}
