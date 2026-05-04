<?php

namespace Database\Seeders;

use App\Models\LabParameter;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class LabParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parameterMap = [
            'CLINICAL CHEMISTRY' => [
                ['name' => 'Sodium', 'code' => 'Na', 'unit' => 'mmol/L', 'reference_range' => '135 - 145'],
                ['name' => 'Potassium', 'code' => 'K', 'unit' => 'mmol/L', 'reference_range' => '3.5 - 5.0'],
                ['name' => 'Chloride', 'code' => 'Cl', 'unit' => 'mmol/L', 'reference_range' => '98 - 107'],
                ['name' => 'Bicarbonate', 'code' => 'HCO3', 'unit' => 'mmol/L', 'reference_range' => '22 - 29'],
                ['name' => 'Urea', 'code' => 'Urea', 'unit' => 'mg/dL', 'reference_range' => '7 - 20'],
                ['name' => 'Creatinine', 'code' => 'Cr', 'unit' => 'mg/dL', 'reference_range' => '0.6 - 1.4'],
                ['name' => 'Glucose', 'code' => 'Glu', 'unit' => 'mg/dL', 'reference_range' => '70 - 140'],
                ['name' => 'Calcium', 'code' => 'Ca', 'unit' => 'mg/dL', 'reference_range' => '8.5 - 10.5'],
                ['name' => 'Total Bilirubin', 'code' => 'TBil', 'unit' => 'mg/dL', 'reference_range' => '0.2 - 1.2'],
                ['name' => 'Direct Bilirubin', 'code' => 'DBil', 'unit' => 'mg/dL', 'reference_range' => '0.0 - 0.3'],
                ['name' => 'AST', 'code' => 'AST', 'unit' => 'U/L', 'reference_range' => '10 - 40'],
                ['name' => 'ALT', 'code' => 'ALT', 'unit' => 'U/L', 'reference_range' => '7 - 56'],
                ['name' => 'ALP', 'code' => 'ALP', 'unit' => 'U/L', 'reference_range' => '44 - 147'],
                ['name' => 'Total Protein', 'code' => 'TP', 'unit' => 'g/dL', 'reference_range' => '6.0 - 8.3'],
                ['name' => 'Albumin', 'code' => 'Alb', 'unit' => 'g/dL', 'reference_range' => '3.5 - 5.0'],
            ],
            'HAEMATOLOGY' => [
                ['name' => 'White Blood Cell Count', 'code' => 'WBC', 'unit' => 'x10^9/L', 'reference_range' => '4.0 - 10.0'],
                ['name' => 'Red Blood Cell Count', 'code' => 'RBC', 'unit' => 'x10^12/L', 'reference_range' => '4.0 - 5.8'],
                ['name' => 'Hemoglobin', 'code' => 'Hb', 'unit' => 'g/dL', 'reference_range' => '12 - 16'],
                ['name' => 'Packed Cell Volume', 'code' => 'PCV', 'unit' => '%', 'reference_range' => '36 - 54'],
                ['name' => 'Mean Corpuscular Volume', 'code' => 'MCV', 'unit' => 'fL', 'reference_range' => '80 - 100'],
                ['name' => 'Mean Corpuscular Hemoglobin', 'code' => 'MCH', 'unit' => 'pg', 'reference_range' => '27 - 33'],
                ['name' => 'Mean Corpuscular Hemoglobin Concentration', 'code' => 'MCHC', 'unit' => 'g/dL', 'reference_range' => '32 - 36'],
                ['name' => 'Platelet Count', 'code' => 'PLT', 'unit' => 'x10^9/L', 'reference_range' => '150 - 400'],
                ['name' => 'Neutrophils', 'code' => 'NEUT', 'unit' => '%', 'reference_range' => '40 - 75'],
                ['name' => 'Lymphocytes', 'code' => 'LYMPH', 'unit' => '%', 'reference_range' => '20 - 45'],
                ['name' => 'Monocytes', 'code' => 'MONO', 'unit' => '%', 'reference_range' => '2 - 10'],
                ['name' => 'Eosinophils', 'code' => 'EOS', 'unit' => '%', 'reference_range' => '1 - 6'],
                ['name' => 'Basophils', 'code' => 'BASO', 'unit' => '%', 'reference_range' => '0 - 1'],
                ['name' => 'Erythrocyte Sedimentation Rate', 'code' => 'ESR', 'unit' => 'mm/hr', 'reference_range' => '0 - 20'],
            ],
            'MICROBIOLOGY' => [
                ['name' => 'Macroscopy', 'code' => 'MAC', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Microscopy', 'code' => 'MIC', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Pus Cells', 'code' => 'PUS', 'unit' => '/hpf', 'reference_range' => '0 - 5'],
                ['name' => 'Red Blood Cells', 'code' => 'RBC', 'unit' => '/hpf', 'reference_range' => '0 - 2'],
                ['name' => 'Epithelial Cells', 'code' => 'EPI', 'unit' => '/hpf', 'reference_range' => 'Few'],
                ['name' => 'Yeast Cells', 'code' => 'YEAST', 'unit' => null, 'reference_range' => 'Nil'],
                ['name' => 'Bacteria', 'code' => 'BACT', 'unit' => null, 'reference_range' => 'Nil to scanty'],
                ['name' => 'Culture Isolate', 'code' => 'CULT', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Antibiotic Sensitivity', 'code' => 'SENS', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Comment', 'code' => 'COMMENT', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
            ],
            'SEROLOGY' => [
                ['name' => 'HBsAg', 'code' => 'HBsAg', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'HCV Antibody', 'code' => 'HCV Ab', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'HIV I & II', 'code' => 'HIV', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'VDRL', 'code' => 'VDRL', 'unit' => null, 'reference_range' => 'Non Reactive'],
                ['name' => 'Rheumatoid Factor', 'code' => 'RF', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'ASO Titre', 'code' => 'ASOT', 'unit' => 'IU/mL', 'reference_range' => '< 200'],
                ['name' => 'Typhoid IgM', 'code' => 'Typhi IgM', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'Typhoid IgG', 'code' => 'Typhi IgG', 'unit' => null, 'reference_range' => 'Negative'],
            ],
            'PARASITOLOGY' => [
                ['name' => 'Malaria Parasite', 'code' => 'MP', 'unit' => null, 'reference_range' => 'Not Seen'],
                ['name' => 'Parasite Density', 'code' => 'Density', 'unit' => '/uL', 'reference_range' => null],
                ['name' => 'Ova', 'code' => 'OVA', 'unit' => null, 'reference_range' => 'Not Seen'],
                ['name' => 'Cysts', 'code' => 'CYST', 'unit' => null, 'reference_range' => 'Not Seen'],
                ['name' => 'Trophozoites', 'code' => 'TROPH', 'unit' => null, 'reference_range' => 'Not Seen'],
                ['name' => 'Occult Blood', 'code' => 'OB', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'Worm Segments', 'code' => 'WORM', 'unit' => null, 'reference_range' => 'Not Seen'],
            ],
            'HISTOPATHOLOGY' => [
                ['name' => 'Specimen Received', 'code' => 'SPEC', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Gross Description', 'code' => 'GROSS', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Microscopic Description', 'code' => 'MICRO', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Diagnosis', 'code' => 'DX', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
                ['name' => 'Comment', 'code' => 'COMMENT', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
            ],
            'LIPID PROFILE' => [
                ['name' => 'Total Cholesterol', 'code' => 'TC', 'unit' => 'mg/dL', 'reference_range' => '< 200'],
                ['name' => 'Triglycerides', 'code' => 'TG', 'unit' => 'mg/dL', 'reference_range' => '< 150'],
                ['name' => 'HDL Cholesterol', 'code' => 'HDL', 'unit' => 'mg/dL', 'reference_range' => '> 40'],
                ['name' => 'LDL Cholesterol', 'code' => 'LDL', 'unit' => 'mg/dL', 'reference_range' => '< 100'],
                ['name' => 'VLDL Cholesterol', 'code' => 'VLDL', 'unit' => 'mg/dL', 'reference_range' => '< 30'],
            ],
            'IMMUNOLOGY' => [
                ['name' => 'CRP', 'code' => 'CRP', 'unit' => 'mg/L', 'reference_range' => '< 10'],
                ['name' => 'ESR', 'code' => 'ESR', 'unit' => 'mm/hr', 'reference_range' => '0 - 20'],
                ['name' => 'ANA', 'code' => 'ANA', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'Anti-dsDNA', 'code' => 'dsDNA', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'ANCA', 'code' => 'ANCA', 'unit' => null, 'reference_range' => 'Negative'],
            ],
            'ENDOCRINOLOGY' => [
                ['name' => 'TSH', 'code' => 'TSH', 'unit' => 'mIU/L', 'reference_range' => '0.4 - 4.0'],
                ['name' => 'Free T4', 'code' => 'FT4', 'unit' => 'ng/dL', 'reference_range' => '0.8 - 1.8'],
                ['name' => 'Free T3', 'code' => 'FT3', 'unit' => 'pg/mL', 'reference_range' => '2.3 - 4.2'],
                ['name' => 'HbA1c', 'code' => 'HbA1c', 'unit' => '%', 'reference_range' => '< 5.7'],
                ['name' => 'Insulin', 'code' => 'INS', 'unit' => 'uIU/mL', 'reference_range' => '2 - 25'],
            ],
            'PRENATAL SCREENING' => [
                ['name' => 'Blood Group', 'code' => 'BG', 'unit' => null, 'reference_range' => null],
                ['name' => 'Rh Factor', 'code' => 'Rh', 'unit' => null, 'reference_range' => null],
                ['name' => 'Antibody Screen', 'code' => 'ABSC', 'unit' => null, 'reference_range' => 'Negative'],
                ['name' => 'TORCH Panel', 'code' => 'TORCH', 'unit' => null, 'reference_range' => null, 'input_type' => 'textarea'],
            ],
        ];

        foreach ($parameterMap as $categoryName => $parameters) {
            $category = ServiceCategory::where('name', $categoryName)->first();

            if (!$category) {
                continue;
            }

            foreach ($parameters as $index => $parameter) {
                LabParameter::firstOrCreate(
                    [
                        'service_category_id' => $category->id,
                        'name' => $parameter['name'],
                    ],
                    [
                        'tenant_id' => $category->tenant_id ?? null,
                        'code' => $parameter['code'] ?? null,
                        'unit' => $parameter['unit'] ?? null,
                        'reference_range' => $parameter['reference_range'] ?? null,
                        'input_type' => $parameter['input_type'] ?? 'text',
                        'display_order' => $parameter['display_order'] ?? $index,
                        'is_required' => $parameter['is_required'] ?? false,
                        'status' => $parameter['status'] ?? true,
                    ]
                );
            }
        }
    }
}
