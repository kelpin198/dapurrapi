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
            'items.*.makanan_id' => 'required|exists:makanans,id',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'sometimes|required|in:bank_transfer,qris,cash_on_pickup',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
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
