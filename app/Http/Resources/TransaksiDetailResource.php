<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaksiDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'makanan_id' => $this->makanan_id,
            'nama_makanan' => $this->whenLoaded('makanan', fn () => $this->makanan->nama_makanan),
            'qty' => $this->qty,
            'harga_satuan' => (float) $this->harga_satuan,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
