<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ulasans extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'makanan_id',
        'minuman_id',
        'nama_menu',
        'rating',
        'komentar',
        'balasan_admin',
    ];

    protected function casts(): array
    {
        return ['rating' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function makanan(): BelongsTo
    {
        return $this->belongsTo(Makanans::class, 'makanan_id');
    }

    public function minuman(): BelongsTo
    {
        return $this->belongsTo(Minuman::class, 'minuman_id');
    }
}
