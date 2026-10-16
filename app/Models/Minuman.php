<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Minuman extends Model
{
    use SoftDeletes;

    protected $table = 'minumans';

    protected $fillable = ['nama_minuman', 'kategori_id', 'harga', 'stok', 'deskripsi'];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategoris::class, 'kategori_id');
    }
}
