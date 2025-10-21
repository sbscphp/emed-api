<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\PatientVisit;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\PharmacySupply;
use App\Models\Radiology;
use App\Models\Surgery;
use App\Models\Treatment;
use App\Models\User;
use App\Repositories\Pharmacy\PharmacyInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Class PharmacyService
 * 
 * This class provides services related to Pharmacy operations and acts as a 
 * layer between the Controller and the PharmacyRepository.
 */
class PharmacyService
{
    protected PharmacyInterface $PharmacyInterface;
    /**
     * Pharmacy constructor.
     * 
     * @param PharmacyInterface $PharmacyInterface
     */

    /**
     * Retrieve all Pharmacy.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = PatientVisit::query()
            ->where('tenant_id', $tenantId)
            // ->where('status', PatientVisitStatusEnums::TREATMENT->value)
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
            ->with(['patient', 'triage:id,visit_id,severity']);

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $totalMedicationInStock = Medication::where('tenant_id', $tenantId)->count();
        $availableMedication = Medication::where('tenant_id', $tenantId)->where('medicine_status', GeneralEnums::AVAILABLE->value)->count();
        $fulfilledPrescriptions = Treatment::where('tenant_id', $tenantId)->where('status', GeneralEnums::FULLFILLED->value)->count();
        $totalSupply = PharmacySupply::where('tenant_id', $tenantId)->count();
        $totalRequest = PharmacyRequest::where('tenant_id', $tenantId)->count();
        $totalPharmacy = Pharmacy::where('tenant_id', $tenantId)->count();
        return [
            'totalMedicationInStock' => $totalMedicationInStock,
            'availableMedication' => $availableMedication,
            'fulfilledPrescriptions' => $fulfilledPrescriptions,
            'totalSupply' => $totalSupply,
            'totalRequest' => $totalRequest,
            'totalPharmacy' => $totalPharmacy
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Firstname'      => $visit->patient->firstname ?? 'N/A',
                'Lastname'       => $visit->patient->lastname ?? 'N/A',
                'Card No'        => $visit->patient->cardno ?? 'N/A',
                'Patient Type'        => $visit->patient->reg_status ?? 'N/A',
                'Patient No'     => $visit->patient->patientno ?? 'N/A',
                'Visit Number'     => $visit->visitno ?? 'N/A',
                // 'Arrival Date'   => $visit->arrival_date ?? 'N/A',
                // 'Patient Status' => $visit->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_visits.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_visits.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function patientTreatmentOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $query = Treatment::query()
            ->where('tenant_id', $tenantId)
            ->where('visit_id', $request->visit_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('pharmacy', 'name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('drug', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->whereRelation('billingLogDetail', 'status', $request['payment_status']);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', $dateFilter);
            })
            ->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })
            ->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with(['pharmacyRequest.pharmacy', 'billingLogDetail']);

        // Handle pagination vs export
        if (!empty($request['paginate']) && empty($request['export'])) {
            $records = $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        } else {
            $records = $query->orderBy('id', 'DESC')->get();
        }

        // Attach consulted user from landlord DB
        $records->each(function ($item) {
            if ($item->consultation && $item->consultation->consulted_by) {
                $consultedUser = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($item->consultation->consulted_by);

                $item->consultedBy = $consultedUser;
            } else {
                $item->consultedBy = null;
            }
        });

        return $records;
    }

    public function patientTreatmentsExport($records, $format)
    {
        $exportData = $records->map(function ($treatment) {
            return [
                'Pharmacy Name'      => $treatment->medication->pharmacy->name ?? 'N/A',
                'Medicine Name'      => $treatment->medication->medicine_name ?? 'N/A',
                'Quantity'        => $treatment->quantity ?? 'N/A',
                'Dosage'     => $treatment->dosage ?? 'N/A',
                'Payment Status'     => $treatment->billingLogDetail->status ?? 'N/A',
                'Prescribed On'   => $treatment->created_at ?? 'N/A',
                'Status'   => $treatment->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_treatments.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_treatments.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function fulfillTreatment($data)
    {
        try {
            $currentUser = Auth::user();
            $treatment = Treatment::find($data['treatment_id']);
            $drug = PharmacyRequest::find($treatment->drug_id);

            $treatment->update([
                'dispensed_by' => $currentUser->id,
                'dispensing_date' => $data['dispensing_date'],
                'quantity_dispensed' => $data['quantity_dispensed'],
                'batch_number' => $data['batch_number'],
                'expiry_date' => $data['expiry_date'],
                'status' => GeneralEnums::FULLFILLED->value
            ]);
            // Update drug stock
            $qtyAvailable = max(0, $drug->quantity_available - $data['quantity_dispensed']);
            $drug->quantity_available = $qtyAvailable;
            $drug->quantity_dispensed = $drug->quantity_dispensed + $data['quantity_dispensed'];

            if ($qtyAvailable <= 0) {
                $drug->stock_level = GeneralEnums::OUT_OF_STOCK->value;
            }

            $drug->save();
            return $treatment->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createPharmacy($request)
    {
        try {
            $currentUser = Auth::user();

            $data = $request->validated();
            $tenantId = $request->header('X-Tenant-ID');
            $data['created_by']  = $currentUser->id;
            $data['tenant_id']  = $tenantId;
            $data['pharmacy_id'] = $this->generatePharmacyId();

            // Create pharmacy record
            $pharmacy = Pharmacy::create($data);

            return $pharmacy;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function pharmacyOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = Pharmacy::query()->where('tenant_id', $tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('pharmacy_id', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('state', 'state_name', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with(['state']);

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function pharmacyExport($records, $format)
    {
        $exportData = $records->map(function ($pharmacy) {
            return [
                'Pharmacy Name'      => $pharmacy->name ?? 'N/A',
                'Pharmacy ID'        => $pharmacy->pharmacy_id ?? 'N/A',
                'Location'        => $pharmacy->state->state_name ?? 'N/A',
                'Contact'     => $pharmacy->phone_number ?? 'N/A',
                'License Number'   => $pharmacy->license_number ?? 'N/A',
                'Operating Hours' => $pharmacy->opening_time ?? 'N/A',
                'Status' => $pharmacy->active == 1 ? 'Active' : 'Inactive' ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'pharmacy.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('pharmacy.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function updatePharmacy($request, $id)
    {
        try {
            $pharmacy = Pharmacy::findOrFail($id);

            $data = $request;

            // Update pharmacy record
            $pharmacy->update($data);

            return $pharmacy->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function generatePharmacyId(): string
    {
        $latest = Pharmacy::latest('id')->first();
        $nextNumber = $latest ? $latest->id + 1 : 1;
        return 'PHA-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
