<?php

namespace App\Repositories\Consultation;

use App\Models\Consultation;
use App\Models\PatientVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultationRepository implements ConsultationInterface
{
    /**
     * Retrieve a collection of Consultation from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Consultation::all();
    }


    /**
     * Create new Consultation in the database.
     *
     * @param array $data
     * @return \App\Models\Consultation
     */
    public function create(array $data)
    {
        return Consultation::create($data);
    }


    /**
     * Update an existing Consultation in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function update(array $data, $id)
    {
        $record = Consultation::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Consultation from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Consultation::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Consultation in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function find($id)
    {
        return Consultation::find($id);
    }


    /**
     * Find an existing Consultation in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Consultation
     */
    public function findByAttribute($attr, $value)
    {
        return Consultation::where($attr, $value)->first();
    }

    public function getPatients()
    {
        // $date = $date ? Carbon::parse($date) : Carbon::today();
        $query = PatientVisit::query();
        $query->select(
            'patient_id',
            'visitno',
            'arrival_date',
            'stage',
            'status'
        );

        $query->whereDate('arrival_date', now()->toDateString());
        $query->where('stage', 'consultation');
        $query->where('status', 'waiting');
        $query->orderBy('created_at', 'asc');
        $query->limit(10);

        return $query->get();
    }

    public function findByVisitNoLabOrBoth($visitno)
    {
        $consultation  = Consultation::where('visit',$visitno)
                        ->where('investigation','laboratory')
                        ->orWhere('investigation','both')
                        ->first();

        return $consultation;
    }

    public function findByVisitNoRadiologyOrBoth($visitno)
    {
        $consultation  = Consultation::where('visit',$visitno)
                        ->where('investigation','radiology')
                        ->orWhere('investigation','both')
                        ->first();

        return $consultation;
    }
}
