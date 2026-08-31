<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantUuid = optional(app()->bound('currentTenant') ? app('currentTenant') : null)->uuid;

        $departments = [
            'General Medicine',
            'Ophthalmology',
            'Cardiology',
            'Neurology',
            'Paediatrics',
            'Obstetrics and Gynaecology',
            'Orthopaedics',
            'Dermatology',
            'Dentistry',
            'Psychiatry',
            // 'Radiology',
            // 'Laboratory',
            // 'Pharmacy',
            'Physiotherapy',
            'Ear, Nose and Throat',
            'Urology',
            'Oncology',
            'Nephrology',
            'Gastroenterology',
            'Emergency',
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['name' => $department, 'tenant_uuid' => $tenantUuid],
                ['status' => true]
            );
        }
    }
}
