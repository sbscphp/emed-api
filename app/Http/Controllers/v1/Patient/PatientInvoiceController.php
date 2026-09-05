<?php

namespace App\Http\Controllers\v1\Patient;

use App\Http\Controllers\Controller;
use App\Models\BillingLog;
use App\Responser\JsonResponser;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\Request;

class PatientInvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * List the authenticated patient's invoices. Every query is scoped to
     * auth('patient')->id so a patient can only ever see their own bills.
     */
    public function index(Request $request)
    {
        try {
            $patientId = auth('patient')->id();

            $query = BillingLog::with(['billingLogDetails', 'transactions'])
                ->where('patient_id', $patientId);

            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->query('payment_status'));
            }

            $query->latest();

            $records = $request->boolean('paginate', true)
                ? $query->paginate($request->query('per_page', 20))
                : $query->get();

            return JsonResponser::send(false, 'Invoice(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while fetching invoices.', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {
            $patientId = auth('patient')->id();

            $invoice = BillingLog::with(['billingLogDetails.serviceUnit', 'transactions'])
                ->where('patient_id', $patientId)
                ->where('id', $id)
                ->first();

            if (!$invoice) {
                return JsonResponser::send(true, 'Invoice not found.', [], 404);
            }

            return JsonResponser::send(false, 'Invoice found successfully', $invoice);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while fetching the invoice.', [], 500, $th);
        }
    }

    /**
     * Download the authenticated patient's own invoice as a PDF.
     */
    public function downloadInvoice($id)
    {
        try {
            $invoice = BillingLog::where('patient_id', auth('patient')->id())
                ->where('id', $id)
                ->first();

            if (!$invoice) {
                return JsonResponser::send(true, 'Invoice not found.', [], 404);
            }

            return $this->invoiceService->download($invoice);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while downloading the invoice.', [], 500, $th);
        }
    }
}
