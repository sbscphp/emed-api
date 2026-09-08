<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Profile\AllergyRequest;
use App\Http\Requests\Patient\Profile\ContactRequest;
use App\Http\Requests\Patient\Profile\MedicalConditionRequest;
use App\Http\Requests\Patient\Profile\UpdateHealthInformationRequest;
use App\Http\Requests\Patient\Profile\UpdatePersonalInformationRequest;
use App\Http\Resources\Patient\AllergyResource;
use App\Http\Resources\Patient\ContactResource;
use App\Http\Resources\Patient\MedicalConditionResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Profile\PatientProfileService;
use Throwable;

/**
 * The "My Profile" module of the patient mobile app.
 *
 * Four things live behind that screen: who the patient is, the health
 * information they keep on themselves, their allergies and long term
 * conditions, and the people to call in an emergency. Linked hospitals and
 * account settings are their own endpoints under the auth and account modules,
 * because they belong to the account rather than to this hospital's record.
 */
class ProfileController extends Controller
{
    public function __construct(protected PatientProfileService $profileService) {}

    /**
     * GET /v1/patient/profile
     *
     * The profile header and the counts under each section.
     */
    public function index()
    {
        try {
            return JsonResponser::send(
                false,
                'Profile retrieved successfully.',
                $this->profileService->overview(),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/profile/personal-information
     */
    public function personalInformation()
    {
        try {
            return JsonResponser::send(
                false,
                'Personal information retrieved successfully.',
                $this->profileService->personalInformation(),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/profile/personal-information
     */
    public function updatePersonalInformation(UpdatePersonalInformationRequest $request)
    {
        try {
            $data = $this->profileService->updatePersonalInformation($request->validated());

            return JsonResponser::send(false, 'Your information has been updated successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/profile/health-information
     *
     * Blood group and genotype.
     */
    public function healthInformation()
    {
        try {
            return JsonResponser::send(
                false,
                'Health information retrieved successfully.',
                [
                    ...$this->profileService->healthInformation(),
                    // The pickers on the edit screen, so the app does not carry
                    // its own copy of the two lists.
                    'options' => [
                        'blood_groups' => UpdateHealthInformationRequest::BLOOD_GROUPS,
                        'genotypes' => UpdateHealthInformationRequest::GENOTYPES,
                    ],
                ],
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/profile/health-information
     */
    public function updateHealthInformation(UpdateHealthInformationRequest $request)
    {
        try {
            $data = $this->profileService->updateHealthInformation($request->validated());

            return JsonResponser::send(false, 'Your health information has been updated successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/profile/allergies
     */
    public function allergies()
    {
        try {
            return JsonResponser::send(
                false,
                'Allergies retrieved successfully.',
                AllergyResource::collection($this->profileService->allergies()),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/profile/allergies
     */
    public function storeAllergy(AllergyRequest $request)
    {
        try {
            $record = $this->profileService->storeAllergy($request->validated());

            return JsonResponser::send(false, 'Allergy added successfully.', new AllergyResource($record), 201);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/profile/allergies/{id}
     */
    public function updateAllergy(AllergyRequest $request, $id)
    {
        try {
            $record = $this->profileService->updateAllergy($id, $request->validated());

            return JsonResponser::send(false, 'Allergy updated successfully.', new AllergyResource($record), 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * DELETE /v1/patient/profile/allergies/{id}
     */
    public function destroyAllergy($id)
    {
        try {
            $this->profileService->deleteAllergy($id);

            return JsonResponser::send(false, 'Allergy removed successfully.', [], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/profile/medical-conditions
     */
    public function medicalConditions()
    {
        try {
            return JsonResponser::send(
                false,
                'Medical conditions retrieved successfully.',
                MedicalConditionResource::collection($this->profileService->medicalConditions()),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/profile/medical-conditions
     */
    public function storeMedicalCondition(MedicalConditionRequest $request)
    {
        try {
            $record = $this->profileService->storeMedicalCondition($request->validated());

            return JsonResponser::send(
                false,
                'Medical condition added successfully.',
                new MedicalConditionResource($record),
                201
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/profile/medical-conditions/{id}
     */
    public function updateMedicalCondition(MedicalConditionRequest $request, $id)
    {
        try {
            $record = $this->profileService->updateMedicalCondition($id, $request->validated());

            return JsonResponser::send(
                false,
                'Medical condition updated successfully.',
                new MedicalConditionResource($record),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * DELETE /v1/patient/profile/medical-conditions/{id}
     */
    public function destroyMedicalCondition($id)
    {
        try {
            $this->profileService->deleteMedicalCondition($id);

            return JsonResponser::send(false, 'Medical condition removed successfully.', [], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/profile/contacts
     *
     * Both lists of the "Emergency contact" screen at once, because the screen
     * shows them together.
     */
    public function contacts()
    {
        try {
            $contacts = $this->profileService->contacts();

            return JsonResponser::send(false, 'Contacts retrieved successfully.', [
                'next_of_kin' => ContactResource::collection($contacts['next_of_kin']),
                'emergency_contact' => ContactResource::collection($contacts['emergency_contact']),
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/profile/contacts/{type}
     *
     * `type` is next_of_kin or emergency_contact.
     */
    public function storeContact(ContactRequest $request, $type)
    {
        try {
            $record = $this->profileService->storeContact($type, $request->validated());

            return JsonResponser::send(false, 'Contact saved successfully.', new ContactResource($record), 201);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/profile/contacts/{type}/{id}
     */
    public function updateContact(ContactRequest $request, $type, $id)
    {
        try {
            $record = $this->profileService->updateContact($type, $id, $request->validated());

            return JsonResponser::send(false, 'Contact updated successfully.', new ContactResource($record), 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * DELETE /v1/patient/profile/contacts/{type}/{id}
     */
    public function destroyContact($type, $id)
    {
        try {
            $this->profileService->deleteContact($type, $id);

            return JsonResponser::send(false, 'Contact removed successfully.', [], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
