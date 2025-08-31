<?php

namespace App\Services\Revamp;

use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\Triage;
use App\Repositories\Consultation\ConsultationInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Class ConsultationService
 *
 * This class provides services related to Consultation operations and acts as a
 * layer between the Controller and the ConsultationRepository.
 */
class ConsultationService
{
    protected ConsultationInterface $ConsultationInterface;
    /**
     * Consultation constructor.
     *
     * @param ConsultationInterface $ConsultationInterface
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = PatientVisit::query()
            ->where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['patient_status']), function ($query) use ($request) {
                $query->where('status', $request['patient_status']);
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_status', $request['payment_status']);
            })
            ->when(!empty($request['payment_method']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_method', $request['payment_method']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('arrival_date', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('arrival_date', 'DESC');
            })
            ->with('patient', 'service', 'patientBilling');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = PatientVisit::query();

        $awaitingConsultation = (clone $query)->where('status', PatientVisitStatusEnums::TRIAGE->value)->count();
        $completedConsultation = (clone $query)->where('status', PatientVisitStatusEnums::CONSULTATION->value)->count();
        $awaitingConsultation = (clone $query)->where('status', PatientVisitStatusEnums::CONSULTATION->value)->count();
        $pendingPatients = PatientVisit::where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)->count();
        $totalOrders = (clone $query)->count();
        $patientLog = (clone $query)->count();

        return [
            'pendingPatients' => $pendingPatients,
            'totalOrders' => $totalOrders,
            'patientLog' => $patientLog,
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Firstname'      => $visit->patient->firstname ?? '',
                'Lastname'       => $visit->patient->lastname ?? '',
                'Card No'        => $visit->patient->cardno ?? '',
                'Patient No'     => $visit->patient->patientno ?? '',
                'Arrival Date'   => $visit->arrival_date ?? '',
                'Patient Status' => $visit->status ?? '',
                'Payment Method'  => $visit->patientBilling->payment_method ?? 'N/A',
                'Payment Status'    => $visit->patientBilling->payment_status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Retrieve all Consultation.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->ConsultationInterface->all();
    }

    /**
     * Create a new Consultation using the data provided.
     *
     * @param array $data
     * @return \App\Models\Consultation
     */

    public function createConsultation($request)
    {
        try {

            $currentUser = Auth::user();
            $visit = PatientVisit::find($request->visit_id);
            $patient = Patient::find($request->patient_id);
            $request['consulted_by'] = $currentUser->id;
            // Initiate Patient Consultation
            $consultation = Consultation::updateOrCreate(
                ['visit_id' => $request['visit_id']], // unique key to match on
                $request->all() // data to update or insert
            );

            $visit->update([
                'status' => PatientVisitStatusEnums::CONSULTATION->value,
            ]);

            $status =    $request['admit_patient'] == 1 ? PatientVisitStatusEnums::ADMITTED->value : PatientVisitStatusEnums::NOT_ADMITTED->value;
            // update patient registaration staus
            $patient->update([
                'status' => $status,
            ]);

            return $consultation;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createLabTestConsultation($request)
    {
        try {

            $currentUser = Auth::user();
            $visit = PatientVisit::find($request->visit_id);
            $patient = Patient::find($request->patient_id);
            $request['consulted_by'] = $currentUser->id;
            // Initiate Patient Consultation
            $consultation = Consultation::updateOrCreate(
                ['visit_id' => $request['visit_id']], // unique key to match on
                $request->all() // data to update or insert
            );

            $visit->update([
                'status' => PatientVisitStatusEnums::CONSULTATION->value,
            ]);

            $status =    $request['admit_patient'] == 1 ? PatientVisitStatusEnums::ADMITTED->value : PatientVisitStatusEnums::NOT_ADMITTED->value;
            // update patient registaration staus
            $patient->update([
                'status' => $status,
            ]);

            return $consultation;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update an existing Consultation with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function update(array $data, $id)
    {
        return $this->ConsultationInterface->update($data, $id);
    }


    /**
     * Delete a Consultation by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->ConsultationInterface->delete($id);
    }


    /**
     * Find a Consultation by their ID.
     *
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function find($id)
    {
        return $this->ConsultationInterface->find($id);
    }


    /**
     * Find an existing Consultation  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Consultation
     */
    public function findByAttribute($attr, $value)
    {
        return $this->ConsultationInterface->findByAttribute($attr, $value);
    }

    public function getPatients($search, $sortBy, $stage, $status, $paginate, $perPage)
    {
        return $this->ConsultationInterface->getPatients($search, $sortBy, $stage, $status, $paginate, $perPage);
    }

    public function findByVisitNoLabOrBoth($visitno)
    {
        return $this->ConsultationInterface->findByVisitNoLabOrBoth($visitno);
    }

    public function findByVisitNoRadiologyOrBoth($visitno)
    {
        return $this->ConsultationInterface->findByVisitNoRadiologyOrBoth($visitno);
    }
}
