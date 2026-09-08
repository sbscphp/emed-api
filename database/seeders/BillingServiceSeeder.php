<?php

namespace Database\Seeders;

use App\Models\BillingService;
use App\Models\Service;
use App\Models\ServiceUnit;
use Illuminate\Database\Seeder;

/**
 * Seeds the priced sub-services the modules bill against — admissions,
 * consultations and the general services — under the parent service each one
 * belongs to. Pharmacy, laboratory and radiology keep their own catalogues and
 * are deliberately left out.
 */
class BillingServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $tenantId = optional($tenant)->uuid;

        $serviceUnits = collect(['Registration', 'Consultation'])
            ->mapWithKeys(function ($name) use ($tenantId) {
                $unit = ServiceUnit::where('name', $name)
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->first();

                return [$name => optional($unit)->id];
            });

        // Every sub-service hangs off a service, so the three the catalogue
        // is grouped by are made sure of first.
        $parentServices = collect(['Admission', 'Consultation', 'General'])
            ->mapWithKeys(function ($name) use ($tenantId) {
                $service = Service::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $name],
                    ['price' => 0.00, 'status' => true]
                );

                return [$name => $service->id];
            });

        $services = [
            // Admissions
            ['code' => BillingService::CODE_ADMISSION, 'category' => 'Admission', 'name' => 'General Admission Fee', 'price' => 15000.00, 'unit' => 'Registration'],
            ['code' => BillingService::CODE_EMERGENCY_ADMISSION, 'category' => 'Admission', 'name' => 'Emergency Admission Fee', 'price' => 25000.00, 'unit' => 'Registration'],
            ['code' => 'MATERNITY_ADMISSION', 'category' => 'Admission', 'name' => 'Maternity Admission Fee', 'price' => 30000.00, 'unit' => 'Registration'],
            ['code' => 'SURGERY_ADMISSION', 'category' => 'Admission', 'name' => 'Surgical Admission Fee', 'price' => 35000.00, 'unit' => 'Registration'],
            ['code' => 'OBSERVATION_ADMISSION', 'category' => 'Admission', 'name' => 'Observation Admission Fee', 'price' => 10000.00, 'unit' => 'Registration'],
            ['code' => 'DAY_CASE_ADMISSION', 'category' => 'Admission', 'name' => 'Day Case Admission Fee', 'price' => 8000.00, 'unit' => 'Registration'],
            ['code' => 'ICU_ADMISSION', 'category' => 'Admission', 'name' => 'Intensive Care Unit Admission Fee', 'price' => 75000.00, 'unit' => 'Registration'],

            // Consultations
            ['code' => 'CONSULTATION', 'category' => 'Consultation', 'name' => 'General Consultation', 'price' => 5000.00, 'unit' => 'Consultation'],
            ['code' => 'SPECIALIST_CONSULTATION', 'category' => 'Consultation', 'name' => 'Specialist Consultation', 'price' => 15000.00, 'unit' => 'Consultation'],
            ['code' => 'CONSULTANT_REVIEW', 'category' => 'Consultation', 'name' => 'Consultant Ward Review', 'price' => 10000.00, 'unit' => 'Consultation'],
            ['code' => 'FOLLOW_UP_CONSULTATION', 'category' => 'Consultation', 'name' => 'Follow Up Consultation', 'price' => 3000.00, 'unit' => 'Consultation'],
            ['code' => 'TELE_CONSULTATION', 'category' => 'Consultation', 'name' => 'Tele Consultation', 'price' => 4000.00, 'unit' => 'Consultation'],
            ['code' => 'EMERGENCY_CONSULTATION', 'category' => 'Consultation', 'name' => 'Emergency Consultation', 'price' => 12000.00, 'unit' => 'Consultation'],
            ['code' => 'ANTENATAL_CONSULTATION', 'category' => 'Consultation', 'name' => 'Antenatal Consultation', 'price' => 6000.00, 'unit' => 'Consultation'],

            // General hospital services
            ['code' => 'REGISTRATION', 'category' => 'General', 'name' => 'Patient Registration / Card', 'price' => 2000.00, 'unit' => 'Registration'],
            ['code' => 'CARD_REPLACEMENT', 'category' => 'General', 'name' => 'Hospital Card Replacement', 'price' => 1500.00, 'unit' => 'Registration'],
            ['code' => 'TRIAGE', 'category' => 'General', 'name' => 'Triage / Vital Signs Check', 'price' => 1000.00, 'unit' => 'Registration'],
            ['code' => 'NURSING_CARE', 'category' => 'General', 'name' => 'Daily Nursing Care', 'price' => 5000.00, 'unit' => 'Registration'],
            ['code' => 'WOUND_DRESSING', 'category' => 'General', 'name' => 'Wound Dressing', 'price' => 3500.00, 'unit' => 'Registration'],
            ['code' => 'INJECTION_ADMIN', 'category' => 'General', 'name' => 'Injection Administration', 'price' => 1500.00, 'unit' => 'Registration'],
            ['code' => 'IV_INFUSION', 'category' => 'General', 'name' => 'Intravenous Infusion', 'price' => 4000.00, 'unit' => 'Registration'],
            ['code' => 'OXYGEN_THERAPY', 'category' => 'General', 'name' => 'Oxygen Therapy (per hour)', 'price' => 5000.00, 'unit' => 'Registration'],
            ['code' => 'CATHETERISATION', 'category' => 'General', 'name' => 'Urethral Catheterisation', 'price' => 7000.00, 'unit' => 'Registration'],
            ['code' => 'SUTURING', 'category' => 'General', 'name' => 'Suturing / Stitching', 'price' => 8000.00, 'unit' => 'Registration'],
            ['code' => 'DRESSING_PACK', 'category' => 'General', 'name' => 'Sterile Dressing Pack', 'price' => 2500.00, 'unit' => 'Registration'],
            ['code' => 'ECG', 'category' => 'General', 'name' => 'Electrocardiogram (ECG)', 'price' => 12000.00, 'unit' => 'Registration'],
            ['code' => 'PHYSIOTHERAPY_SESSION', 'category' => 'General', 'name' => 'Physiotherapy Session', 'price' => 10000.00, 'unit' => 'Registration'],
            ['code' => 'AMBULANCE', 'category' => 'General', 'name' => 'Ambulance Service', 'price' => 25000.00, 'unit' => 'Registration'],
            ['code' => 'MEDICAL_REPORT', 'category' => 'General', 'name' => 'Medical Report', 'price' => 10000.00, 'unit' => 'Registration'],
            ['code' => 'MEDICAL_CERTIFICATE', 'category' => 'General', 'name' => 'Medical Certificate of Fitness', 'price' => 5000.00, 'unit' => 'Registration'],
            ['code' => 'MINOR_PROCEDURE', 'category' => 'General', 'name' => 'Minor Theatre Procedure', 'price' => 40000.00, 'unit' => 'Registration'],
            ['code' => 'MAJOR_PROCEDURE', 'category' => 'General', 'name' => 'Major Theatre Procedure', 'price' => 150000.00, 'unit' => 'Registration'],
            ['code' => 'DELIVERY_NORMAL', 'category' => 'General', 'name' => 'Normal Delivery', 'price' => 80000.00, 'unit' => 'Registration'],
            ['code' => 'DELIVERY_CAESAREAN', 'category' => 'General', 'name' => 'Caesarean Section', 'price' => 250000.00, 'unit' => 'Registration'],
            ['code' => 'MORTUARY_DAILY', 'category' => 'General', 'name' => 'Mortuary Service (per day)', 'price' => 10000.00, 'unit' => 'Registration'],
        ];

        foreach ($services as $service) {
            // firstOrCreate so a re-run never resets a price the tenant has adjusted.
            BillingService::firstOrCreate(
                ['code' => $service['code'], 'tenant_id' => $tenantId],
                [
                    'service_id' => $parentServices[$service['category']],
                    'service_unit_id' => $serviceUnits[$service['unit']] ?? null,
                    'category' => $service['category'],
                    'name' => $service['name'],
                    'price' => $service['price'],
                    'status' => true,
                ]
            );
        }
    }
}
