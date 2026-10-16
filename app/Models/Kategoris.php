<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kategoris extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama_kategori'];

    public function makanans()
    {
        return $this->hasMany(Makanans::class, 'kategori_id');
    }

    public function minumans(): HasMany
    {
        return $this->hasMany(Minuman::class, 'kategori_id');
    }
}
