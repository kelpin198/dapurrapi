<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUlasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'makanan_id' => ['required_without:minuman_id', 'nullable', 'integer', 'exists:makanans,id'],
            'minuman_id' => ['required_without:makanan_id', 'nullable', 'integer', 'exists:minumans,id'],
            'rating' => ['required', 'numeric', 'decimal:0,1', 'between:1,10'],
            'komentar' => 'nullable|string|max:2000',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('makanan_id') && $this->filled('minuman_id')) {
                    $validator->errors()->add('makanan_id', 'Pilih makanan atau minuman, bukan keduanya.');
                    $validator->errors()->add('minuman_id', 'Pilih makanan atau minuman, bukan keduanya.');
                }
            },
        ];
    }
}
