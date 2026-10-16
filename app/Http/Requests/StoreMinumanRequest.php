<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMinumanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'nama_minuman' => ['required', 'string', 'max:150', 'unique:minumans,nama_minuman'],
            'kategori_id' => [
                'required',
                Rule::exists('kategoris', 'id')->where(
                    fn ($query) => $query->where('nama_kategori', 'like', 'Minuman %'),
                ),
            ],
            'harga' => ['required', 'numeric', 'min:0'],
            'stok' => ['required', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }
}
