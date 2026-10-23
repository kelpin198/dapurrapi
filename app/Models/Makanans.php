<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Makanans extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama_makanan', 'kategori_id', 'harga', 'stok', 'deskripsi'];

    public function kategori()
    {
        return $this->belongsTo(Kategoris::class);
    }

    public function ulasans()
    {
        return $this->hasMany(Ulasans::class, 'makanan_id');
    }
}
