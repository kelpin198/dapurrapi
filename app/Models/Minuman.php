<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Minuman extends Model
{
    use SoftDeletes;

    protected $table = 'minumans';

    protected $fillable = ['nama_minuman', 'harga', 'stok', 'deskripsi'];
}
