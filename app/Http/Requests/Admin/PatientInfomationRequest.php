<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Multitenancy\Models\Tenant;

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
            'phoneno' => 'required',
            'age' => 'required|integer',
            'gender' => 'required|string',
            'marital_status' => 'required|string',
            'email' => ['required', 'email', 'max:255', 'unique:tenant.patients,email'],
            'homeaddress' => 'required|string',
            'occupation' => 'required|string',
            'allergies' => 'nullable',
            // 'bloodgroup' => 'required|string',
            // 'cardno' => 'nullable|unique:patients,cardno',
            // 'genotype' => 'required|string',
            // 'referral' => 'required|string',

            // 'nokfirstname' => 'required|string',
            // 'noklastname' => 'required|string',
            // 'nokgender' => 'required|string',
            // 'nokphoneno' => 'required|alpha_num',
            // 'nokstateoforigin' => 'nullable|string',
            // 'noklga' => 'nullable|string',
            // 'nokhomeaddress' => 'required|string',
            // 'nokrelationship' => 'required|string',
            // 'emgfirstname' => 'required|string',
            // 'emglastname' => 'required|string',
            // 'emggender' => 'required|string',
            // 'emgphoneno' => 'required|alpha_num',
            // 'emgstateoforigin' => 'nullable|string',
            // 'emglga' => 'nullable|string',
            // 'emghomeaddress' => 'required|string',
            // 'emgrelationship' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'The first name is required.',
            'firstname.string' => 'The first name must be a valid string.',
            'firstname.max' => 'The first name may not be greater than 255 characters.',

            'lastname.required' => 'The last name is required.',
            'lastname.string' => 'The last name must be a valid string.',
            'lastname.max' => 'The last name may not be greater than 255 characters.',

            'dob.required' => 'The date of birth is required.',
            'dob.date' => 'The date of birth must be a valid date.',

            'phoneno.required' => 'The phone number is required.',

            'age.required' => 'The age is required.',
            'age.integer' => 'The age must be a valid integer.',

            'gender.required' => 'The gender is required.',
            'gender.string' => 'The gender must be a valid string.',

            'marital_status.required' => 'The marital status is required.',
            'marital_status.string' => 'The marital status must be a valid string.',

            'email.required' => 'The email address is required.',
            'email.email' => 'The email address must be a valid email.',
            'email.max' => 'The email may not be greater than 255 characters.',

            'homeaddress.required' => 'The home address is required.',
            'homeaddress.string' => 'The home address must be a valid string.',

            'occupation.required' => 'The occupation is required.',
            'occupation.string' => 'The occupation must be a valid string.',

            // 'bloodgroup.required' => 'The blood group is required.',
            // 'bloodgroup.string' => 'The blood group must be a valid string.',

            // 'cardno.unique' => 'This card number already exists.',

            // 'genotype.required' => 'The genotype is required.',
            // 'genotype.string' => 'The genotype must be a valid string.',

            // 'referral.required' => 'The referral is required.',
            // 'referral.string' => 'The referral must be a valid string.',

            // 'nokfirstname.required' => 'The next of kin first name is required.',
            // 'nokfirstname.string' => 'The next of kin first name must be a valid string.',

            // 'noklastname.required' => 'The next of kin last name is required.',
            // 'noklastname.string' => 'The next of kin last name must be a valid string.',

            // 'nokgender.required' => 'The next of kin gender is required.',
            // 'nokgender.string' => 'The next of kin gender must be a valid string.',

            // 'nokphoneno.required' => 'The next of kin phone number is required.',
            // 'nokphoneno.alpha_num' => 'The next of kin phone number must contain only letters and numbers.',

            // 'nokstateoforigin.string' => 'The next of kin state of origin must be a valid string.',

            // 'noklga.string' => 'The next of kin LGA must be a valid string.',

            // 'nokhomeaddress.required' => 'The next of kin home address is required.',
            // 'nokhomeaddress.string' => 'The next of kin home address must be a valid string.',

            // 'nokrelationship.required' => 'The next of kin relationship is required.',
            // 'nokrelationship.string' => 'The next of kin relationship must be a valid string.',

            // 'emgfirstname.required' => 'The emergency contact first name is required.',
            // 'emgfirstname.string' => 'The emergency contact first name must be a valid string.',

            // 'emglastname.required' => 'The emergency contact last name is required.',
            // 'emglastname.string' => 'The emergency contact last name must be a valid string.',

            // 'emggender.required' => 'The emergency contact gender is required.',
            // 'emggender.string' => 'The emergency contact gender must be a valid string.',

            // 'emgphoneno.required' => 'The emergency contact phone number is required.',
            // 'emgphoneno.alpha_num' => 'The emergency contact phone number must contain only letters and numbers.',

            // 'emgstateoforigin.string' => 'The emergency contact state of origin must be a valid string.',

            // 'emglga.string' => 'The emergency contact LGA must be a valid string.',

            // 'emghomeaddress.required' => 'The emergency contact home address is required.',
            // 'emghomeaddress.string' => 'The emergency contact home address must be a valid string.',

            // 'emgrelationship.required' => 'The emergency contact relationship is required.',
            // 'emgrelationship.string' => 'The emergency contact relationship must be a valid string.',
        ];
    }
}
