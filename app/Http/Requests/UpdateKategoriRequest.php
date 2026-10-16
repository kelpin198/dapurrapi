<?php

namespace App\Http\Requests;

use App\Models\Minuman;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateKategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nama_kategori' => "required|string|max:100|unique:kategoris,nama_kategori,{$id}",
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasMinumans = Minuman::withTrashed()
                ->where('kategori_id', $this->route('id'))
                ->exists();
            $namaKategori = mb_strtolower((string) $this->input('nama_kategori'));

            if ($hasMinumans && ! str_starts_with($namaKategori, 'minuman ')) {
                $validator->errors()->add(
                    'nama_kategori',
                    'Kategori yang digunakan minuman harus tetap diawali dengan Minuman.',
                );
            }
        }];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors(),
        ], 422));
    }
}
