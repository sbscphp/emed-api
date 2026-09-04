<?php

namespace App\Services\Patient\Profile;

use App\Exceptions\PatientAppException;
use App\Models\EmergencyContact;
use App\Models\NextOfKin;
use App\Models\PatientAllergy;
use App\Models\Patient;
use App\Models\PatientMedicalCondition;
use App\Services\Patient\Concerns\ResolvesPatientProfile;
use App\Services\Patient\PatientContextService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Class PatientProfileService
 *
 * The "My Profile" module of the patient mobile app: who the patient is, the
 * health information they keep on themselves, and the people to call if
 * something happens to them.
 *
 * Everything written here lands on the patient record at the current hospital.
 * The landlord account behind it — the email and password they sign in with —
 * is deliberately left alone: that account is shared with every other hospital
 * that registered them, and editing a profile at one hospital should not change
 * how they sign in to another. Account level changes have their own endpoints
 * under /patient/change-password and /patient/biometric.
 *
 * The one thing written outside the patient record is the date of birth on the
 * membership row for this hospital, which is per hospital rather than shared and
 * is kept in step so the two cannot disagree — see ResolvesPatientProfile.
 */
class PatientProfileService
{
    use ResolvesPatientProfile;

    /**
     * The two kinds of person the emergency section keeps.
     *
     * @var array<int, string>
     */
    public const CONTACT_TYPES = ['next_of_kin', 'emergency_contact'];

    public function __construct(protected PatientContextService $context) {}

    /**
     * The profile header and the sections beneath it.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $patient = $this->context->patient();
        $user = $this->context->user();
        $tenant = $this->context->tenant();

        return [
            'name' => trim($patient->firstname . ' ' . $patient->lastname),
            'patient_id' => $patient->patientno,
            'card_no' => $patient->cardno,
            'profile_picture' => $user->profile_picture,
            'primary_hospital' => [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'logo' => $tenant->logo,
            ],
            'counts' => [
                'linked_hospitals' => $user->tenants()->count(),
                'allergies' => $this->allergyQuery()->count(),
                'medical_conditions' => $this->conditionQuery()->count(),
                'next_of_kin' => $this->nextOfKinQuery()->count(),
                'emergency_contacts' => $this->emergencyContactQuery()->count(),
            ],
        ];
    }

    /**
     * The "Personal information" screen.
     *
     * @return array<string, mixed>
     */
    public function personalInformation(): array
    {
        $patient = $this->context->patient();

        return [
            'full_name' => trim($patient->firstname . ' ' . $patient->lastname),
            'first_name' => $patient->firstname,
            'last_name' => $patient->lastname,
            'middle_name' => $patient->middlename,
            // Resolved rather than read straight off the record: the account
            // carries a date of birth of its own, and a patient registered
            // through one form and filed through another can have it there and
            // not here. Reading only the record is what made this screen show
            // null while the login response showed a date.
            'date_of_birth' => $this->dob($patient),
            'date_of_birth_label' => $this->toDate($this->dob($patient), 'd F Y'),
            'gender' => $patient->gender,
            'phone_number' => $patient->phoneno,
            'email' => $patient->email,
            'home_address' => $patient->homeaddress,
            'marital_status' => $patient->marital_status,
            // Shown so the patient can see which address they sign in with, and
            // read only because it belongs to the account rather than to this
            // hospital's copy of their details.
            'account_email' => $this->context->user()->email,
        ];
    }

    /**
     * Save the "Personal information" form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePersonalInformation(array $data): array
    {
        $patient = $this->context->patient();
        $changes = [];

        // The form asks for one full name; the record keeps two columns.
        if (array_key_exists('full_name', $data) && !empty($data['full_name'])) {
            [$first, $last] = $this->splitName($data['full_name']);
            $changes['firstname'] = $first;
            $changes['lastname'] = $last;
        }

        foreach ([
            'date_of_birth' => 'dob',
            'gender' => 'gender',
            'phone_number' => 'phoneno',
            'email' => 'email',
            'home_address' => 'homeaddress',
            'marital_status' => 'marital_status',
        ] as $field => $column) {
            if (array_key_exists($field, $data)) {
                $changes[$column] = $data[$field];
            }
        }

        if (array_key_exists('dob', $changes) && !empty($changes['dob'])) {
            $dob = Carbon::parse($changes['dob']);
            $changes['dob'] = $dob->toDateString();
            // The column is written by the admin registration form too, which
            // keeps them in step rather than letting age drift from the date.
            $changes['age'] = $dob->age;
        }

        if (!empty($changes)) {
            $patient->update($changes);
            $patient->refresh();
        }

        // The account keeps its own copy, written when the patient registered.
        // Saving to one and not the other is what let the two drift apart in the
        // first place, so a new date is written to both.
        if (!empty($changes['dob'])) {
            $this->syncAccountDob($changes['dob']);
        }

        return $this->personalInformation();
    }

    /**
     * Carry a new date of birth onto the account's membership row.
     *
     * Never allowed to fail the save: the hospital's record is the one the
     * patient just corrected, and it has already been written by the time this
     * runs.
     */
    protected function syncAccountDob(string $dob): void
    {
        try {
            $tenantUser = $this->context->tenantUser();

            if ($tenantUser && (string) $tenantUser->date_of_birth !== $dob) {
                $tenantUser->forceFill(['date_of_birth' => $dob])->save();
            }
        } catch (\Throwable $th) {
            Log::warning('Could not carry a new date of birth onto the patient account.', [
                'patient_id' => $this->context->patient()->id,
                'exception' => $th->getMessage(),
            ]);
        }
    }

    /**
     * The "Blood group and Genotype" screen.
     *
     * @return array<string, mixed>
     */
    public function healthInformation(): array
    {
        $patient = $this->context->patient();

        return [
            'blood_group' => $patient->bloodgroup,
            'genotype' => $patient->genotype,
        ];
    }

    /**
     * Save the "Blood group and Genotype" form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateHealthInformation(array $data): array
    {
        $patient = $this->context->patient();
        $changes = [];

        if (array_key_exists('blood_group', $data)) {
            $changes['bloodgroup'] = $data['blood_group'];
        }

        if (array_key_exists('genotype', $data)) {
            $changes['genotype'] = $data['genotype'];
        }

        if (!empty($changes)) {
            $patient->update($changes);
        }

        return $this->healthInformation();
    }

    /* ---------------------------------------------------------------------
     | Allergies
     |---------------------------------------------------------------------*/

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PatientAllergy>
     */
    public function allergies()
    {
        return $this->allergyQuery()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \App\Models\PatientAllergy
     */
    public function storeAllergy(array $data): PatientAllergy
    {
        return PatientAllergy::create([
            'tenant_id' => $this->context->tenantUuid(),
            'patient_id' => $this->context->patient()->id,
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'reaction' => $data['reaction'] ?? null,
            'source' => 'Patient',
            'recorded_by' => $this->context->user()->id,
        ]);
    }

    /**
     * @param  int  $id
     * @param  array<string, mixed>  $data
     * @return \App\Models\PatientAllergy
     */
    public function updateAllergy($id, array $data): PatientAllergy
    {
        $allergy = $this->findAllergy($id);
        $changes = [];

        if (!empty($data['name'])) {
            $changes['name'] = $data['name'];
        }

        if (!empty($data['type'])) {
            $changes['type'] = $data['type'];
        }

        // Sent at all rather than sent non-empty: a reaction the patient no
        // longer wants recorded is cleared by sending it empty.
        if (array_key_exists('reaction', $data)) {
            $changes['reaction'] = $data['reaction'];
        }

        if (!empty($changes)) {
            $allergy->update($changes);
        }

        return $allergy->refresh();
    }

    /**
     * @param  int  $id
     * @return void
     */
    public function deleteAllergy($id): void
    {
        $this->findAllergy($id)->delete();
    }

    /* ---------------------------------------------------------------------
     | Medical conditions
     |---------------------------------------------------------------------*/

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PatientMedicalCondition>
     */
    public function medicalConditions()
    {
        return $this->conditionQuery()
            ->orderByRaw('diagnosed_at IS NULL')
            ->orderBy('diagnosed_at', 'DESC')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \App\Models\PatientMedicalCondition
     */
    public function storeMedicalCondition(array $data): PatientMedicalCondition
    {
        return PatientMedicalCondition::create([
            'tenant_id' => $this->context->tenantUuid(),
            'patient_id' => $this->context->patient()->id,
            'name' => $data['name'],
            'diagnosed_at' => $this->diagnosedAt($data['diagnosed_at'] ?? null),
            'notes' => $data['notes'] ?? null,
            'source' => 'Patient',
            'recorded_by' => $this->context->user()->id,
        ]);
    }

    /**
     * @param  int  $id
     * @param  array<string, mixed>  $data
     * @return \App\Models\PatientMedicalCondition
     */
    public function updateMedicalCondition($id, array $data): PatientMedicalCondition
    {
        $condition = $this->findCondition($id);
        $changes = [];

        if (!empty($data['name'])) {
            $changes['name'] = $data['name'];
        }

        if (array_key_exists('diagnosed_at', $data)) {
            $changes['diagnosed_at'] = $this->diagnosedAt($data['diagnosed_at']);
        }

        if (array_key_exists('notes', $data)) {
            $changes['notes'] = $data['notes'];
        }

        if (!empty($changes)) {
            $condition->update($changes);
        }

        return $condition->refresh();
    }

    /**
     * @param  int  $id
     * @return void
     */
    public function deleteMedicalCondition($id): void
    {
        $this->findCondition($id)->delete();
    }

    /* ---------------------------------------------------------------------
     | Next of kin and emergency contacts
     |---------------------------------------------------------------------*/

    /**
     * Both lists of the "Emergency contact" screen.
     *
     * @return array<string, \Illuminate\Database\Eloquent\Collection>
     */
    public function contacts(): array
    {
        return [
            'next_of_kin' => $this->nextOfKinQuery()->orderBy('id')->get(),
            'emergency_contact' => $this->emergencyContactQuery()->orderBy('id')->get(),
        ];
    }

    /**
     * @param  string  $type  next_of_kin or emergency_contact
     * @param  array<string, mixed>  $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function storeContact(string $type, array $data): Model
    {
        [$first, $last] = $this->splitName($data['name']);

        $attributes = [
            'patient_id' => $this->context->patient()->id,
            'firstname' => $first,
            'lastname' => $last,
            // The tables ask for a gender the app's form does not, so it is
            // stored as unspecified rather than refusing the save.
            'gender' => $data['gender'] ?? 'Unspecified',
            'phoneno' => $data['phone_number'],
            'relationship' => $data['relationship'],
            'homeaddress' => $data['address'] ?? null,
        ];

        return $this->contactModel($type)::create($attributes);
    }

    /**
     * @param  string  $type
     * @param  int  $id
     * @param  array<string, mixed>  $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateContact(string $type, $id, array $data): Model
    {
        $contact = $this->findContact($type, $id);
        $changes = [];

        if (!empty($data['name'])) {
            [$first, $last] = $this->splitName($data['name']);
            $changes['firstname'] = $first;
            $changes['lastname'] = $last;
        }

        foreach ([
            'phone_number' => 'phoneno',
            'relationship' => 'relationship',
            'address' => 'homeaddress',
            'gender' => 'gender',
        ] as $field => $column) {
            if (array_key_exists($field, $data)) {
                $changes[$column] = $data[$field];
            }
        }

        if (!empty($changes)) {
            $contact->update($changes);
        }

        return $contact->refresh();
    }

    /**
     * @param  string  $type
     * @param  int  $id
     * @return void
     */
    public function deleteContact(string $type, $id): void
    {
        $this->findContact($type, $id)->delete();
    }

    /* ---------------------------------------------------------------------
     | Internals
     |---------------------------------------------------------------------*/

    /**
     * @throws \App\Exceptions\PatientAppException
     */
    protected function findAllergy($id): PatientAllergy
    {
        $allergy = $this->allergyQuery()->find($id);

        if (!$allergy) {
            throw new PatientAppException('We could not find that allergy.', 404);
        }

        return $allergy;
    }

    /**
     * @throws \App\Exceptions\PatientAppException
     */
    protected function findCondition($id): PatientMedicalCondition
    {
        $condition = $this->conditionQuery()->find($id);

        if (!$condition) {
            throw new PatientAppException('We could not find that medical condition.', 404);
        }

        return $condition;
    }

    /**
     * @throws \App\Exceptions\PatientAppException
     */
    protected function findContact(string $type, $id): Model
    {
        $contact = $this->contactQuery($type)->find($id);

        if (!$contact) {
            throw new PatientAppException('We could not find that contact.', 404);
        }

        return $contact;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function allergyQuery()
    {
        return PatientAllergy::query()->forPatient($this->context->patient()->id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function conditionQuery()
    {
        return PatientMedicalCondition::query()->forPatient($this->context->patient()->id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function nextOfKinQuery()
    {
        return NextOfKin::query()->where('patient_id', $this->context->patient()->id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function emergencyContactQuery()
    {
        return EmergencyContact::query()->where('patient_id', $this->context->patient()->id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function contactQuery(string $type)
    {
        $this->assertContactType($type);

        return $type === 'next_of_kin' ? $this->nextOfKinQuery() : $this->emergencyContactQuery();
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function contactModel(string $type): string
    {
        $this->assertContactType($type);

        return $type === 'next_of_kin' ? NextOfKin::class : EmergencyContact::class;
    }

    /**
     * The type comes off the URL, so an unknown one is refused rather than
     * quietly falling through to whichever list is written last.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function assertContactType(string $type): void
    {
        if (!in_array($type, self::CONTACT_TYPES, true)) {
            throw new PatientAppException(
                'A contact is either a next_of_kin or an emergency_contact.',
                422
            );
        }
    }

    /**
     * Split the one name the form asks for into the two columns the tables keep.
     *
     * Everything after the first word is the surname, so "Mary Ann Sikiru" keeps
     * "Ann Sikiru" together rather than losing the middle of it.
     *
     * @return array{0:string, 1:string}
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }

    /**
     * The patient's date of birth for this screen.
     *
     * Falls back to the date the account was registered with when the hospital's
     * own record has none, so the profile screen and the login response cannot
     * answer differently. See ResolvesPatientProfile for why there are two.
     */
    protected function dob(Patient $patient): ?string
    {
        return $this->resolveDob($patient, $this->context->tenantUser());
    }

    /**
     * A diagnosis date the patient gives as a month and a year, stored as the
     * first of that month.
     *
     * @param  string|null  $value
     * @return string|null
     */
    protected function diagnosedAt($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfMonth()->toDateString();
        } catch (\Throwable $th) {
            throw new PatientAppException('That diagnosis date could not be understood.', 422);
        }
    }
}
