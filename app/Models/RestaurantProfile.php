<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantProfile extends Model
{
    protected $fillable = ['name', 'history', 'logo_data', 'logo_mime_type'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'Dapur Rapi', 'history' => '']
        );
    }

    public function logoDataUrl(): ?string
    {
        if (! $this->logo_data || ! $this->logo_mime_type) {
            return null;
        }

        return 'data:'.$this->logo_mime_type.';base64,'.$this->logo_data;
    }
}
