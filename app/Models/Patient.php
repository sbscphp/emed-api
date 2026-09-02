<?php

namespace App\Models;

use Azeemade\BulkUpload\Concerns\Uploadable;
use Azeemade\BulkUpload\Contracts\BulkUploadable;
use App\Helpers\GeneralHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PatientDocument;
use Spatie\Multitenancy\Models\Tenant;

class Patient extends Model implements BulkUploadable
{
    use HasFactory, SoftDeletes, Uploadable;

    protected array $tempMetadata = [];

    /**
     * Receive metadata from the upload request (tenant_id, created_by, etc.)
     */
    public function setBulkUploadMetadata(array $metadata): void
    {
        $this->tempMetadata = $metadata;
    }

    /**
     * Validation rules for each CSV row.
     */
    public function getUploadValidationRules(array $row): array
    {
        return [
            'firstname'        => 'required|string|max:255',
            'lastname'         => 'required|string|max:255',
            'dob'              => 'nullable|date',
            'phoneno'          => 'nullable|string|max:30',
            'age'              => 'nullable|integer|min:0',
            'gender'           => 'nullable|string|in:Male,Female,Other',
            'marital_status'   => 'nullable|string|max:50',
            'email'            => 'nullable|email|max:255',
            'lga'              => 'nullable|string|max:100',
            'stateoforigin'    => 'nullable|string|max:100',
            'homeaddress'      => 'nullable|string|max:500',
            'occupation'       => 'nullable|string|max:255',
            'religion'         => 'nullable|string|max:100',
            'tribe'            => 'nullable|string|max:100',
            'bloodgroup'       => 'nullable|string|max:10',
            'cardno'           => 'nullable|string|max:100',
            'genotype'         => 'nullable|string|max:10',
            'referral'         => 'nullable|string|max:255',
            // Next of Kin (all optional)
            'nok_firstname'    => 'nullable|string|max:255',
            'nok_lastname'     => 'nullable|string|max:255',
            'nok_gender'       => 'nullable|string|in:Male,Female,Other',
            'nok_phoneno'      => 'nullable|string|max:30',
            'nok_stateoforigin' => 'nullable|string|max:100',
            'nok_lga'          => 'nullable|string|max:100',
            'nok_homeaddress'  => 'nullable|string|max:500',
            'nok_relationship' => 'nullable|string|max:100',
        ];
    }

    /**
     * Template column headers for the downloadable CSV.
     */
    public function getTemplateColumns(): array
    {
        return [
            // Patient fields
            'firstname',
            'lastname',
            'dob',
            'phoneno',
            'age',
            'gender',
            'marital_status',
            'email',
            'lga',
            'stateoforigin',
            'homeaddress',
            'occupation',
            'religion',
            'tribe',
            'bloodgroup',
            'cardno',
            'genotype',
            'referral',
            // Next of Kin (optional)
            'nok_firstname',
            'nok_lastname',
            'nok_gender',
            'nok_phoneno',
            'nok_stateoforigin',
            'nok_lga',
            'nok_homeaddress',
            'nok_relationship',
        ];
    }

    /**
     * Sample row shown in the template.
     */
    public function getTemplateSample(): array
    {
        return [
            'firstname'         => 'John',
            'lastname'          => 'Doe',
            'dob'               => '1990-05-15',
            'phoneno'           => '08012345678',
            'age'               => '34',
            'gender'            => 'Male',
            'marital_status'    => 'Single',
            'email'             => 'john.doe@example.com',
            'lga'               => 'Ikeja',
            'stateoforigin'     => 'Lagos',
            'homeaddress'       => '12 Sample Street, Lagos',
            'occupation'        => 'Engineer',
            'religion'          => 'Christianity',
            'tribe'             => 'Yoruba',
            'bloodgroup'        => 'O+',
            'cardno'            => '',
            'genotype'          => 'AA',
            'referral'          => '',
            // NOK sample
            'nok_firstname'     => 'Jane',
            'nok_lastname'      => 'Doe',
            'nok_gender'        => 'Female',
            'nok_phoneno'       => '08098765432',
            'nok_stateoforigin' => 'Lagos',
            'nok_lga'           => 'Surulere',
            'nok_homeaddress'   => '12 Sample Street, Lagos',
            'nok_relationship'  => 'Spouse',
        ];
    }

    /**
     * Return empty array to prevent a descriptions row from being added to the template.
     * The importer treats every post-header row as data, so a descriptions row would
     * be processed as a patient record and fail validation.
     */
    public function getTemplateOptions(): array
    {
        return [];
    }

    /**
     * Process a single validated CSV row.
     * Creates the Patient and, if NOK data is present, NextOfKin + EmergencyContact.
     */
    public function processUploadRow(array $row): void
    {
        $tenantId  = $this->tempMetadata['tenant_id']  ?? null;
        $createdBy = $this->tempMetadata['created_by'] ?? null;

        // Generate patient number
        $tenant        = Tenant::current();
        $tenantDomain  = $tenant ? $tenant->domain : 'emed';
        $firstTwo      = strtoupper(substr(trim($tenantDomain), 0, 2));
        $tenantAcronym = $firstTwo . 'H';
        $patientno     = 'EMED/'
            . GeneralHelper::generateUniqueRandomId($row['firstname'])
            . '/' . GeneralHelper::generateUniqueRandomId($row['lastname'])
            . '/' . $tenantAcronym;

        $patient = $this->create([
            'tenant_id'      => $tenantId,
            'created_by'     => $createdBy,
            'patientno'      => $patientno,
            'firstname'      => $row['firstname'],
            'lastname'       => $row['lastname'],
            'dob'            => $row['dob']           ?? null,
            'phoneno'        => $row['phoneno']        ?? null,
            'age'            => $row['age']            ?? null,
            'gender'         => $row['gender']         ?? null,
            'marital_status' => $row['marital_status'] ?? null,
            'email'          => $row['email']          ?? null,
            'lga'            => $row['lga']            ?? null,
            'stateoforigin'  => $row['stateoforigin']  ?? null,
            'homeaddress'    => $row['homeaddress']    ?? null,
            'occupation'     => $row['occupation']     ?? null,
            'religion'       => $row['religion']       ?? null,
            'tribe'          => $row['tribe']          ?? null,
            'bloodgroup'     => $row['bloodgroup']     ?? null,
            'cardno'         => $row['cardno']         ?? null,
            'genotype'       => $row['genotype']       ?? null,
            'referral'       => $row['referral']       ?? null,
        ]);

        // Create Next of Kin + Emergency Contact if any NOK field was supplied
        $nokFields = [
            'nok_firstname',
            'nok_lastname',
            'nok_gender',
            'nok_phoneno',
            'nok_stateoforigin',
            'nok_lga',
            'nok_homeaddress',
            'nok_relationship'
        ];

        $hasNok = collect($nokFields)->contains(fn($f) => !empty($row[$f]));

        if ($hasNok) {
            $nokData = [
                'patient_id'    => $patient->id,
                'firstname'     => $row['nok_firstname']     ?? null,
                'lastname'      => $row['nok_lastname']      ?? null,
                'gender'        => $row['nok_gender']        ?? null,
                'phoneno'       => $row['nok_phoneno']       ?? null,
                'stateoforigin' => $row['nok_stateoforigin'] ?? null,
                'lga'           => $row['nok_lga']           ?? null,
                'homeaddress'   => $row['nok_homeaddress']   ?? null,
                'relationship'  => $row['nok_relationship']  ?? null,
            ];

            NextOfKin::create($nokData);
            EmergencyContact::create($nokData);
        }
    }

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $casts = [
        'allergies' => 'array',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * The landlord user account this patient signs into the mobile app with.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nextOfKin()
    {
        return $this->hasOne(NextOfKin::class);
    }

    public function emergencyContact()
    {
        return $this->hasOne(EmergencyContact::class);
    }

    public function visits()
    {
        return $this->hasMany(PatientVisit::class);
    }

    public function visits_recent()
    {
        return $this->hasOne(PatientVisit::class, 'patient_id')->latest();
    }

    public function  patient_visits()
    {
        return $this->hasOne(PatientVisit::class, 'patient_id', 'id');
    }

    public function  patient_visits_latest()
    {
        return $this->hasOne(PatientVisit::class, 'patient_id', 'id')->latest();
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function patientDocuments()
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function triage()
    {
        return $this->hasOne(Triage::class);
    }

    public function medicalHistory()
    {
        return $this->hasMany(MedicalHistory::class);
    }

    public function familyHistory()
    {
        return $this->hasMany(FamilyHistory::class);
    }

    public function socialHistory()
    {
        return $this->hasMany(SocialHistory::class);
    }

    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class, 'patient_id', 'id');
    }

    public function billingLogsForPatient()
    {
        return $this->hasOne(BillingLog::class, 'patient_id', 'id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }


    public function laboratory()
    {
        return $this->hasOne(Laboratory::class, 'patient_id', 'id');
    }


    public function pharmacy()
    {
        return $this->hasOne(Pharmacy::class, 'patient_id', 'id');
    }

    public function radiology()
    {
        return $this->hasOne(Radiology::class, 'patient_id', 'id');
    }
}
