<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class MedicationUpdateRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'generic_name' => 'required|string|max:255',
            'brand_name' => 'required|string|max:255',
            'medicine_name' => 'required|string|max:255',
            'medicine_type' => 'required|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reg_no' => [
                'required',
                'string',
                Rule::exists('tenant.medications', 'reg_no'),
            ],

            'manufacturer' => 'required|string|max:255',
            'medicine_status'  => 'nullable|in:available,about to expire,out of stock,expired',
            'pharmacy_id' => 'nullable|exists:tenant.pharmacies,id',
            'active_ingredient' => 'nullable|string',
            //     'pharmacy_id' => [
            //         'nullable',
            //         'alpha_num',
            //         'max:255',
            //         function ($attribute, $value, $fail) {
            //             $exists = DB::connection('tenant')
            //                 ->table('pharmacies')
            //                 ->where('id', $value)
            //                 ->exists();

            //             if (!$exists) {
            //                 $fail('this pharmacy id does not exist');
            //             }
            //         },
            //     ],
        ];
    }



    public function messages(): array
    {
        return [
            'generic_name.required' => 'Generic name is required.',
            'brand_name.required' => 'Brand name is required.',
            'medicine_name.required' => 'Medicine name is required.',
            'medicine_type.required' => 'Medicine type is required.',
            'cost_price.required' => 'Cost price is required.',
            'cost_price.numeric' => 'Cost price must be a number.',
            'selling_price.required' => 'Selling price is required.',
            'selling_price.numeric' => 'Selling price must be a number.',
            'reg_no.required' => 'Registration number is required.',
            'manufacturer.required' => 'Manufacturer is required.',
            'medicine_status.in' => 'Medicine status must be one of: available, about to expire, out of stock, expired.',
            'pharmacy_id.required' => 'Pharmacy ID is required.',
            'pharmacy_id.exists' => 'The selected pharmacy does not exist.',
        ];
    }
}
