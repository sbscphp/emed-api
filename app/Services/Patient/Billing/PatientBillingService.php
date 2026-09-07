<?php

namespace App\Services\Patient\Billing;

use App\Exceptions\PatientAppException;
use App\Models\BillingLog;
use App\Models\PatientPayment;
use App\Services\Patient\Concerns\ResolvesDateFilters;
use App\Services\Patient\PatientContextService;
use App\Services\Patient\Reports\PatientReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

/**
 * Class PatientBillingService
 *
 * The patient app's Billing screen: what this hospital has billed the patient
 * for, as one list the `tab` narrows to what is still owed or to what has been
 * settled, and one invoice opened up.
 *
 * Read only as far as the ledger goes. A patient never edits a bill — the
 * hospital raises it and the payment services below move money onto it. What
 * this class does is present billing_logs the way the screen reads them, which
 * the hospital console's own billing service does not, because it answers to a
 * very different set of screens.
 *
 * Outstanding is computed rather than trusted. `amount_outstanding` is left at
 * zero on a good number of historical rows while the invoice is plainly not
 * paid, so the outstanding balance is derived from the grand total less what has
 * been paid, and the stored column is used only as a fallback when a row carries
 * no total at all.
 *
 * @see \App\Services\Patient\Billing\PatientPaymentService for paying one.
 * @see \App\Services\Patient\Reports\PatientReportService for the receipt a
 *      settled bill downloads as.
 */
class PatientBillingService
{
    use ResolvesDateFilters;

    /**
     * The tabs above the list.
     *
     * @var array<int, string>
     */
    public const TABS = ['all', 'outstanding', 'paid'];

    public function __construct(
        protected PatientContextService $context,
        protected PatientReportService $reports,
    ) {}

    /**
     * The Billing screen.
     *
     * One list, whichever tab is showing: `outstanding` narrows it to what is
     * still owed, `paid` to what has been settled, and `all` leaves it whole.
     * The summary above it counts both sides either way, so the screen can label
     * the tab it is not currently listing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function index($request): array
    {
        $tab = strtolower($request['tab'] ?? 'all');

        return [
            'summary' => $this->summary(),

            // The tab travels back with the filter because the list is no longer
            // named after it — a page of bills on its own would not say which
            // side of the ledger it holds.
            'filter' => ['tab' => $tab] + $this->appliedFilter($request),
            'bills' => $this->records($request, $tab),
        ];
    }

    /**
     * The card at the top of the screen: what this hospital is owed, over how
     * many invoices.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $tenant = $this->context->tenant();

        $bills = $this->newQuery()->get();

        $outstanding = $bills->filter(fn($bill) => $this->outstandingFor($bill) > 0);

        return [
            'hospital' => [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'logo' => $tenant->logo,
            ],
            'currency' => config('services.paystack.currency', 'NGN'),
            'outstanding_total' => round($outstanding->sum(fn($bill) => $this->outstandingFor($bill)), 2),
            'outstanding_count' => $outstanding->count(),
            'paid_total' => round($bills->sum(fn($bill) => (float) $bill->amount_paid), 2),
            'paid_count' => $bills->count() - $outstanding->count(),
            'total_billed' => round($bills->sum(fn($bill) => $this->grandTotalFor($bill)), 2),
        ];
    }

    /**
     * The list, narrowed to the tab being shown.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $tab  all, outstanding or paid
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function records($request, string $tab = 'all')
    {
        $dateFilter = $this->dateFilter($request);

        $query = $this->newQuery()
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = $request['search_param'];
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'LIKE', '%' . $search . '%')
                        ->orWhereHas('billingLogDetails', function ($detail) use ($search) {
                            $detail->where('item_name', 'LIKE', '%' . $search . '%');
                        });
                });
            })
            ->when(!empty($request['date']), function ($query) use ($request) {
                $query->whereDate('billing_date', Carbon::parse($request['date'])->toDateString());
            })
            ->when($dateFilter, fn($query) => $query->whereBetween('billing_date', $dateFilter))
            ->orderBy('billing_date', 'DESC')
            ->orderBy('id', 'DESC');

        // Outstanding is derived, so it cannot be a SQL where clause without
        // repeating the derivation in two languages. The volume here is one
        // patient's invoices at one hospital, so it is filtered in PHP and the
        // paginator is built from the result.
        $records = $query->get()
            ->filter(fn($bill) => $this->matchesTab($bill, $tab))
            ->map(fn($bill) => $this->decorate($bill))
            ->values();

        return $this->present($records, $request);
    }

    /**
     * Whether a bill belongs on the tab being shown.
     *
     * An unrecognised tab keeps the bill: `tab` is validated against TABS on the
     * way in, so the only value that reaches here besides the two is `all`.
     */
    protected function matchesTab(BillingLog $bill, string $tab): bool
    {
        if ($tab === 'outstanding') {
            return $this->outstandingFor($bill) > 0;
        }

        if ($tab === 'paid') {
            return $this->outstandingFor($bill) <= 0;
        }

        return true;
    }

    /**
     * Hand the list back the way the request asked for it.
     *
     * Three shapes, in order of specificity. `paginate` gives a page with the
     * totals and links around it, which is what "View all" opens. A bare `limit`
     * gives the first few rows and nothing else, which is what the summary
     * screen shows above "View all". Neither gives the lot.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function present($records, $request)
    {
        if (!empty($request['paginate'])) {
            return $this->paginate($records, $request);
        }

        if (!empty($request['limit'])) {
            return $records->take((int) $request['limit'])->values();
        }

        return $records;
    }

    /**
     * Turn the list into a page.
     *
     * Built from the filtered collection rather than by the query builder,
     * because which side of the ledger a bill falls on is derived in PHP — see
     * the note in records(). The count the paginator reports is therefore the
     * real number of matching invoices, not the number of rows on the page.
     *
     * One list means the ordinary `page` parameter, so this endpoint pages like
     * every other list in the app.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @param  \Illuminate\Http\Request  $request
     */
    protected function paginate($records, $request): LengthAwarePaginator
    {
        $perPage = max(1, (int) ($request['limit'] ?? 15));
        $page = max(1, (int) LengthAwarePaginator::resolveCurrentPage());

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),

                // The filters travel with the links, so page 2 of a search is
                // still that search rather than the unfiltered list.
                'query' => Arr::except($request->query(), ['page']),
            ]
        );
    }

    /**
     * One invoice, with its lines — the Bill Details screen.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): Model
    {
        $bill = $this->newQuery()->find($id);

        if (!$bill) {
            throw new PatientAppException('We could not find that bill.', 404);
        }

        return $this->decorate($bill, true);
    }

    /**
     * "Download Receipt" on a bill the patient has paid.
     *
     * Streamed rather than answered in the JSON envelope, the way the
     * laboratory and radiology reports are, so the app can hand the bytes
     * straight to the file system or a share sheet.
     *
     * A bill still carrying a balance has no receipt to give: what would be on
     * it is the payments list the detail screen already shows.
     *
     * @param  int  $id
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function receipt($id)
    {
        $bill = $this->show($id);

        if (!$bill->is_settled) {
            throw new PatientAppException('A receipt is available once this bill has been paid in full.', 409);
        }

        $pdf = $this->reports->receipt($bill, $this->context->tenant(), $this->context->patient());
        $fileName = $this->receiptFileName($bill);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    /**
     * What the receipt downloads as, named after the invoice it settles.
     */
    protected function receiptFileName(BillingLog $bill): string
    {
        return $this->reports->fileName($bill->invoice_number ?: ('Bill ' . $bill->id), 'Receipt');
    }

    /**
     * The same lookup the payment services use before they charge for a bill.
     *
     * Kept here so "is this bill the signed in patient's, at this hospital" is
     * answered in exactly one place.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function findOwnedBill($id): BillingLog
    {
        $bill = $this->newQuery()->find($id);

        if (!$bill) {
            throw new PatientAppException('We could not find that bill.', 404);
        }

        return $bill;
    }

    /**
     * Every bill the patient still owes something on, oldest first.
     *
     * What "Pay now" on the Outstanding Bills card charges for, and the same set
     * the summary counts — both derive outstanding the same way, so the card and
     * the checkout can never disagree about which invoices are in play.
     *
     * Oldest first because that is the order the money is spread in when it does
     * not cover everything: the debt that has been owed longest clears first,
     * which is also how a part payment settles the lines within one invoice.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\BillingLog>
     */
    public function outstandingBills()
    {
        return $this->newQuery()
            ->orderBy('billing_date')
            ->orderBy('id')
            ->get()
            ->filter(fn($bill) => $this->outstandingFor($bill) > 0)
            ->map(fn($bill) => $this->decorate($bill))
            ->values();
    }

    /**
     * What is still owed on a bill, in naira.
     *
     * Public because the payment services cap a charge at it.
     */
    public function outstandingFor(BillingLog $bill): float
    {
        $total = $this->grandTotalFor($bill);
        $paid = (float) $bill->amount_paid;

        // A row with no total at all is the one case where the stored column is
        // the only thing that knows anything.
        if ($total <= 0) {
            return max(0, round((float) $bill->amount_outstanding, 2));
        }

        return max(0, round($total - $paid, 2));
    }

    /**
     * What the invoice comes to.
     *
     * grand_total is authoritative when it has been set. When it has not — which
     * happens on rows raised by older flows — the lines are summed, because an
     * invoice with lines on it is not a zero naira invoice.
     */
    public function grandTotalFor(BillingLog $bill): float
    {
        $total = (float) $bill->grand_total;

        if ($total > 0) {
            return round($total, 2);
        }

        $total = (float) $bill->total_amount;

        if ($total > 0) {
            return round($total, 2);
        }

        return round((float) $bill->billingLogDetails->sum('amount'), 2);
    }

    /**
     * Bills belonging to the signed in patient at the current hospital.
     *
     * Scoped by patient inside the tenant's own database, for the same reason
     * the diagnostics modules are: billing_logs.tenant_id is nullable and empty
     * on older rows, so filtering on it would quietly hide invoices.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newQuery()
    {
        return BillingLog::query()
            ->where('patient_id', $this->context->patient()->id)
            ->with([
                'billingLogDetails.serviceUnit',
                'service',
                'serviceUnit',
            ]);
    }

    /**
     * Attach what a row cannot answer for itself.
     *
     * The service name the screen titles a bill with is the least obvious part.
     * A bill has no title of its own — it has lines, each belonging to a service
     * unit — so the title is the unit the lines share ("Laboratory services"),
     * or the service type when they do not, or the first line's own name as a
     * last resort.
     *
     * @param  bool  $withPayments  the detail screen also lists what has been
     *                              paid so far, which the list does not need
     */
    protected function decorate(BillingLog $bill, bool $withPayments = false): BillingLog
    {
        $tenant = $this->context->tenant();
        $outstanding = $this->outstandingFor($bill);

        $bill->setAttribute('hospital', [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
            'address' => $tenant->address,
        ]);

        $bill->setAttribute('service_title', $this->serviceTitle($bill));
        $bill->setAttribute('computed_grand_total', $this->grandTotalFor($bill));
        $bill->setAttribute('computed_outstanding', $outstanding);
        $bill->setAttribute('is_settled', $outstanding <= 0);

        // billing_date is a plain string on the model — BillingLog casts
        // nothing, and adding a date cast there would change how every admin
        // endpoint serialises it. Parsed here instead, so the resources have a
        // date to format without each of them reparsing it.
        $bill->setAttribute('billed_at', $this->billedAt($bill));
        $bill->setAttribute('due_date', $this->dueDate($bill));

        if ($withPayments) {
            $bill->setAttribute('payments', $this->paymentsFor($bill));
        }

        // The receipt card the detail screen prints under "Download Receipt" —
        // its file name, size and page count. Only knowable by rendering the
        // document, so it is costed on the detail screen alone and never on a
        // row of the list, and only for a bill that has something to receipt.
        // The render is cached, so the download that follows does not repeat it.
        if ($withPayments && $outstanding <= 0) {
            $bill->setAttribute('receipt', $this->reports->documentMeta(
                $this->reports->receipt($bill, $tenant, $this->context->patient()),
                $this->receiptFileName($bill)
            ));
        }

        return $bill;
    }

    /**
     * What has been paid towards one invoice, newest first.
     *
     * A bulk checkout settles several invoices with one charge, so a bill is
     * found by the whole set a payment covers rather than by billing_id alone,
     * and each row is stamped with `applied_amount` — this bill's share of it.
     * Reading `amount` here would print the entire 85,000 against an invoice
     * that only took 35,000 of it.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\PatientPayment>
     */
    protected function paymentsFor(BillingLog $bill)
    {
        return PatientPayment::query()
            ->forBill($bill->id)
            ->successful()
            ->orderBy('paid_at', 'DESC')
            ->get()
            ->each(fn($payment) => $payment->setAttribute(
                'applied_amount',
                $payment->amountAppliedTo($bill->id)
            ));
    }

    /**
     * What the screen calls this bill.
     */
    protected function serviceTitle(BillingLog $bill): string
    {
        $units = $bill->billingLogDetails
            ->map(fn($detail) => optional($detail->serviceUnit)->name)
            ->filter()
            ->unique();

        if ($units->count() === 1) {
            // "Laboratory" reads as a department on its own; the screen labels
            // it as what was bought.
            return $units->first() . ' services';
        }

        if ($units->count() > 1) {
            return 'Hospital services';
        }

        return optional($bill->service)->name
            ?: optional($bill->serviceUnit)->name
            ?: optional($bill->billingLogDetails->first())->item_name
            ?: 'Hospital bill';
    }

    /**
     * The day the bill was raised, as something the resources can format.
     */
    protected function billedAt(BillingLog $bill): ?Carbon
    {
        $billed = $bill->billing_date ?: $bill->created_at;

        return $billed ? Carbon::parse($billed) : null;
    }

    /**
     * When the bill falls due.
     *
     * The schema has no due date column, and inventing one would mean a
     * migration the hospital console has no way to populate. Fourteen days from
     * the billing date is the interval the design shows (billed 5 June, due 17
     * June is twelve; billed 13 June is the same fortnight), and it is derived
     * rather than stored so it never disagrees with the invoice.
     */
    protected function dueDate(BillingLog $bill): ?string
    {
        $billed = $bill->billing_date ?: $bill->created_at;

        return $billed ? Carbon::parse($billed)->addDays(14)->toDateString() : null;
    }
}
