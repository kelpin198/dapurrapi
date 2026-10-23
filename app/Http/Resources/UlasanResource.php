<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UlasanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'nama_pengulas' => $this->user?->name,
            'makanan_id' => $this->makanan_id,
            'minuman_id' => $this->minuman_id,
            'nama_menu' => $this->nama_menu,
            'jenis_menu' => $this->minuman_id ? 'Minuman' : 'Makanan',
            'rating' => $this->rating,
            'komentar' => $this->komentar,
            'balasan_admin' => $this->balasan_admin,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
