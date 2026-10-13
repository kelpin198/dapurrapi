<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMakananBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:100',
            'items.*.nama_makanan' => 'required|string|max:150',
            'items.*.kategori_id' => 'required|exists:kategoris,id',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.stok' => 'required|integer|min:0',
            'items.*.deskripsi' => 'nullable|string',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors(),
        ], 422));
    }
}
