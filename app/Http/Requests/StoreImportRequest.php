<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_code' => ['required', 'string', 'exists:suppliers,code'],
            'external_import_id' => ['required', 'string'],
            'offers' => ['required', 'array', 'min:1'],
            'offers.*.external_id' => ['required', 'string'],
            'offers.*.property.code' => ['required', 'string', 'exists:properties,code'],
            'offers.*.check_in' => ['required', 'date', 'after_or_equal:today'],
            'offers.*.check_out' => ['required', 'date', 'after:offers.*.check_in'],
            'offers.*.max_guests' => ['required', 'integer', 'min:1'],
            'offers.*.price' => ['required', 'numeric', 'min:0'],
            'offers.*.currency' => ['required', 'string', 'size:3'],
            'offers.*.available_units' => ['required', 'integer', 'min:1'],
            'offers.*.expires_at' => ['nullable', 'date'],
        ];
    }
}
