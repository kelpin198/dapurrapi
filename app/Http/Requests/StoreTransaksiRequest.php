<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.makanan_id' => 'nullable|exists:makanans,id',
            'items.*.minuman_id' => 'nullable|exists:minumans,id',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'sometimes|required|in:bank_transfer,qris,cash_on_pickup',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                $hasFood = filled($item['makanan_id'] ?? null);
                $hasDrink = filled($item['minuman_id'] ?? null);

                if ($hasFood === $hasDrink) {
                    $validator->errors()->add("items.{$index}.makanan_id", 'Pilih tepat satu produk: makanan atau minuman.');
                }
            }

            if ($this->input('payment_method') === 'qris'
                && blank(config('payments.qris_payload'))
                && blank(config('payments.qris_image_url'))) {
                $validator->errors()->add('payment_method', 'QRIS belum dikonfigurasi oleh pengelola.');
            }

            if ($this->input('payment_method') === 'bank_transfer' && count(config('payments.banks', [])) === 0) {
                $validator->errors()->add('payment_method', 'Rekening bank belum dikonfigurasi oleh pengelola.');
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
