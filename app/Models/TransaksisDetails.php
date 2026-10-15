<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksisDetails extends Model
{
    protected $table = 'transaksi_details';

    protected $fillable = ['transaksi_id', 'makanan_id', 'minuman_id', 'nama_produk', 'qty', 'harga_satuan', 'subtotal'];

    public function transaksi()
    {
        return $this->belongsTo(Transaksis::class, 'transaksi_id');
    }

    public function makanan()
    {
        return $this->belongsTo(Makanans::class, 'makanan_id');
    }

    public function minuman()
    {
        return $this->belongsTo(Minuman::class, 'minuman_id');
    }
}
