<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMakananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_makanan' => 'required|string|max:150|unique:makanans,nama_makanan',
            'kategori_id' => 'required|exists:kategoris,id',
            'harga' => 'required|numeric|min:0',
            'stok' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_makanan.required' => 'Nama makanan wajib diisi',
            'nama_makanan.unique' => 'Nama makanan sudah terdaftar',
            'kategori_id.exists' => 'Kategori tidak ditemukan',
            'harga.required' => 'Harga wajib diisi',
            'stok.required' => 'Stok wajib diisi',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors(),
        ], 422));
    }
}
