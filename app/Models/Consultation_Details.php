<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation_Details extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'patient_id',
        'patient_visits_id',
        'complaints',
        'history_of_present_complaints',
        'system_view',
        'provisional_diagnosis',
        'disease_patterns',
        'disease_types',
        'allergies',
        'laboratory',
        'radiology',
        'both',
        'schedule_a_follow_up',
        'referral',
        'relationship_type',
        'chronic_lllness',
        'genetic_disorder',
        'age_of_onset',
        'causes_of death_in_family_member',
        'other_details',

        'occupation',
        'living_situation',
        'substance_use',
        'lifesytle_habits',
        'sexual_history',
        'admit_patient'
    ];
}
