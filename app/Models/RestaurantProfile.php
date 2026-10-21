<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RestaurantProfile extends Model
{
    public const DEFAULT_NAME = 'Dapurrapi';

    public const DEFAULT_HISTORY = "Dapurrapi berawal dari sebuah usaha kuliner rumahan yang dibangun dengan penuh kehangatan, sebagaimana dilambangkan oleh siluet atap rumah pada bagian tengah logonya. Sejak awal berdiri, sang pendiri memiliki prinsip teguh bahwa hidangan yang baik harus memanjakan semua indera. Hal ini mendasari filosofi dan moto utama mereka: \"Sajian Lezat, Penyajian Apik\".\n\nSeiring berjalannya waktu, dedikasi terhadap kebersihan, kerapian, dan kualitas rasa membuat dapur kecil ini berkembang pesat. Transisi menuju standar profesional tingkat tinggi ditandai dengan ikon topi koki (chef's hat) emas bergengsi yang menjadi mahkota merek ini, memposisikan mereka sebagai penyedia \"Kuliner Istimewa\". Ornamen daun-daun hijau yang mengelilingi piring saji hangat mencerminkan komitmen mereka yang tak pernah goyah untuk selalu menggunakan bahan-bahan alami yang segar dan berkualitas.\n\nKini, Dapurrapi bukan lagi sekadar dapur rumahan, melainkan sebuah layanan kuliner yang memiliki semangat inovasi tanpa batas. Dedikasi abadi terhadap dunia gastronomi ini diabadikan secara elegan melalui garis melengkung berbentuk simbol infinity (tak terhingga) yang menyatu harmonis dengan sendok dan garpu.";

    protected $fillable = ['name', 'history', 'logo_data', 'logo_mime_type'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['name' => self::DEFAULT_NAME, 'history' => self::DEFAULT_HISTORY]
        );
    }

    public function logoDataUrl(): ?string
    {
        if (! $this->logo_data || ! $this->logo_mime_type) {
            return null;
        }

        return 'data:'.$this->logo_mime_type.';base64,'.$this->logo_data;
    }

    public static function storedLogoPath(): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $path = 'restaurant-profile/logo.'.$extension;

            if (Storage::disk('local')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function hasLogo(): bool
    {
        return self::storedLogoPath() !== null
            || static::query()->whereNotNull('logo_data')->exists();
    }

    public function logoUrl(): ?string
    {
        return self::storedLogoPath() !== null
            ? route('restaurant-logo')
            : $this->logoDataUrl();
    }
}
