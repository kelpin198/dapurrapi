<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
