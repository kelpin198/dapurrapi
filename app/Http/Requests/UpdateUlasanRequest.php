<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUlasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'decimal:0,1', 'between:1,10'],
            'komentar' => 'nullable|string|max:2000',
        ];
    }
}
