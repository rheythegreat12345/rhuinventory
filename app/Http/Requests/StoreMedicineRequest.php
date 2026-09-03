<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMedicineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medicines.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'medicine_category_id' => ['required', 'exists:medicine_categories,id'],
            'medicine_code' => ['required', 'string', 'max:30', 'unique:medicines,medicine_code'],
            'barcode' => ['nullable', 'string', 'max:80', 'unique:medicines,barcode'],
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:255'],
            'dosage_form' => ['required', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:40'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'maximum_stock_level' => ['required', 'integer', 'gte:minimum_stock_level'],
            'reorder_level' => ['required', 'integer', 'min:0', 'lte:maximum_stock_level'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'reference_price' => ['nullable', 'numeric', 'min:0'],
            'storage_condition' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'medicine_code.unique' => 'That medicine ID is already in use.',
            'maximum_stock_level.gte' => 'Maximum stock must be at least the minimum stock level.',
        ];
    }
}
