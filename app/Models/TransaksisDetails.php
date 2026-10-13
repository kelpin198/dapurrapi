<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksisDetails extends Model
{
    protected $table = 'transaksi_details';

    protected $fillable = ['transaksi_id', 'makanan_id', 'qty', 'harga_satuan', 'subtotal'];

    public function transaksi()
    {
        return $this->belongsTo(Transaksis::class, 'transaksi_id');
    }

    public function makanan()
    {
        return $this->belongsTo(Makanans::class, 'makanan_id');
    }
}
