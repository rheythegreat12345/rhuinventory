<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $medicine = $this->route('medicine');
        $existingMaximum = $medicine?->maximum_stock_level ?? 100;

        $this->merge([
            'dosage_form' => $this->input('dosage_form', $medicine?->dosage_form ?? 'Unspecified'),
            'maximum_stock_level' => max(100, (int) $existingMaximum, (int) $this->input('minimum_stock_level', 10)),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medicines.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $medicine = $this->route('medicine');

        return [
            'medicine_category_id' => ['required', 'exists:medicine_categories,id'],
            'medicine_code' => ['required', 'string', 'max:30', Rule::unique('medicines', 'medicine_code')->ignore($medicine)],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('medicines', 'barcode')->ignore($medicine)],
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:255'],
            'dosage_form' => ['required', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:40'],
            'box_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
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
}
