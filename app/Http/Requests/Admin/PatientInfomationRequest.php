<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PatientInfomationRequest extends FormRequest
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
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'dob' => 'required|date',
            'age' => 'required|integer|min:0',
            'gender' => 'required|in:male,femaela',
            'bloodgroup' => 'required|in:A+, A-, B+, B-, AB+, AB-, O+, O-',
            'bloodgenotype' => 'required|in:AA, AS, SS, AC, SC, CC',
            'email' => 'required|email|unique:patient_information,email',
            'patient_type' => 'required|string',
            'marital_status' => 'required|string',
            'phoneno' => 'required|string|unique:patient_information,phoneno',
            'occupation' => 'required|string',
            'homeaddress' => 'required|string',
            'companyaddress' => 'required|string',
            'religion' => 'nullable|string',
            'stateoforigin' => 'nullable|string',
            'lga' => 'nullable|string',
            'tribe' => 'nullable|string',
            'cardno' => 'required|string|unique:patient_information,cardno',
            'receiptno' => 'nullable|string|unique:patient_information,receiptno',
        ];
    }

    public function messages()
    {
        return [
            'firstname.required' => 'The first name field is required.',
            'firstname.string' => 'The first name must be a valid string.',
            'lastname.required' => 'The last name field is required.',
            'lastname.string' => 'The last name must be a valid string.',
            'dob.required' => 'The date of birth is required.',
            'dob.date' => 'The date of birth must be a valid date.',
            'age.required' => 'The age field is required.',
            'age.min' => 'The age must be at least 0.',
            'gender.required' => 'The gender field is required.',
            'gender.in' => 'The gender must be either male or female.',
            'bloodgroup.required' => 'The blood group field is required.',
            'bloodgroup.in' => 'The blood group must be one of: A+, A-, B+, B-, AB+, AB-, O+, O-.',
            'bloodgenotype.required' => 'The blood genotype field is required.',
            'bloodgenotype.in' => 'The blood genotype must be one of: AA, AS, SS, AC, SC, CC.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'patient_type.required' => 'The patient type field is required.',
            'marital_status.required' => 'The marital status field is required.',
            'phoneno.required' => 'The phone number field is required.',
            'phoneno.string' => 'The phone number must be a valid string.',
            'phoneno.unique' => 'This phone number is already registered.',
            'occupation.required' => 'The occupation field is required.',
            'occupation.string' => 'The occupation must be a valid string.',
            'homeaddress.required' => 'The home address field is required.',
            'companyaddress.required' => 'The company address field is required.',
            'stateoforigin.string' => 'The state of origin must be a valid string.',
            'tribe.string' => 'The tribe must be a valid string.',
            'cardno.required' => 'The card number field is required.',
            'cardno.unique' => 'This card number is already registered.',
            'receiptno.unique' => 'This receipt number is already registered.',
        ];

    }
}
