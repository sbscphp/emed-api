<?php

namespace App\Services\Patient\Billing;

use App\Exceptions\PatientAppException;
use App\Models\BillingLog;
use App\Models\PatientPayment;
use App\Services\Patient\Concerns\ResolvesDateFilters;
use App\Services\Patient\PatientContextService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

/**
 * Class PatientBillingService
 *
 * The patient app's Billing screen: what this hospital has billed the patient
 * for, split into what is still owed and what has been settled, and one invoice
 * opened up.
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

    public function __construct(protected PatientContextService $context) {}

    /**
     * The Billing screen.
     *
     * Returns both lists in one call because the screen shows both at once — an
     * outstanding section with the "Pay now" summary above it, and a paid
     * section under "View all". Asking for them separately would mean two round
     * trips for one screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function index($request): array
    {
        $tab = strtolower($request['tab'] ?? 'all');

        return [
            'summary' => $this->summary(),
            'filter' => $this->appliedFilter($request),

            // A tab narrows the screen to one list. The other side is still
            // answered — empty, and paginated if the request was — so the shape
            // of the response never depends on which tab was asked for.
            'outstanding' => $this->records($request, 'outstanding', $tab === 'paid'),
            'paid' => $this->records($request, 'paid', $tab === 'outstanding'),
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
     * One side of the list.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $side  outstanding or paid
     * @param  bool  $skip  the tab excluded this side; answer empty rather than absent
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function records($request, string $side, bool $skip = false)
    {
        if ($skip) {
            return $this->present(collect(), $request, $side);
        }

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
            ->filter(fn($bill) => $side === 'outstanding'
                ? $this->outstandingFor($bill) > 0
                : $this->outstandingFor($bill) <= 0)
            ->map(fn($bill) => $this->decorate($bill))
            ->values();

        return $this->present($records, $request, $side);
    }

    /**
     * Hand one side back the way the request asked for it.
     *
     * Three shapes, in order of specificity. `paginate` gives a page with the
     * totals and links around it, which is what "View all" opens. A bare `limit`
     * gives the first few rows and nothing else, which is what the summary
     * screen shows above each "View all". Neither gives the lot.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function present($records, $request, string $side)
    {
        if (!empty($request['paginate'])) {
            return $this->paginate($records, $request, $side);
        }

        if (!empty($request['limit'])) {
            return $records->take((int) $request['limit'])->values();
        }

        return $records;
    }

    /**
     * Turn one side into a page.
     *
     * Built from the filtered collection rather than by the query builder,
     * because which side a bill belongs to is derived in PHP — see the note in
     * records(). The count the paginator reports is therefore the real number of
     * matching invoices, not the number of rows on the page.
     *
     * Each side carries its own page parameter. The screen shows both lists at
     * once, and a single `page` would move them together: opening page 2 of the
     * paid list would silently drop the patient's first outstanding bills.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @param  \Illuminate\Http\Request  $request
     */
    protected function paginate($records, $request, string $side): LengthAwarePaginator
    {
        $pageName = $side . '_page';
        $perPage = max(1, (int) ($request['limit'] ?? 15));
        $page = max(1, (int) LengthAwarePaginator::resolveCurrentPage($pageName));

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,

                // The filters travel with the links, so page 2 of a search is
                // still that search rather than the unfiltered list.
                'query' => Arr::except($request->query(), [$pageName]),
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
            $bill->setAttribute(
                'payments',
                PatientPayment::query()
                    ->where('billing_id', $bill->id)
                    ->successful()
                    ->orderBy('paid_at', 'DESC')
                    ->get()
            );
        }

        return $bill;
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
