<?php

namespace App\Services\Patient\Diagnostics;

use App\Exceptions\PatientAppException;
use App\Services\Patient\Concerns\ResolvesDateFilters;
use App\Services\Patient\PatientContextService;
use App\Services\Patient\Reports\PatientReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DiagnosticResultService
 *
 * What the laboratory and radiology modules have in common, which is nearly
 * their whole shape: a test the hospital ordered, a report that is either
 * released or still being worked on, the same All / Released / Pending tabs
 * over it, the same search and date filter, the same previous-reports history
 * for the same test, and the same downloadable PDF.
 *
 * The two modules stay separate — their own service, controller, resources and
 * route group — because their screens, their wording and their result payloads
 * differ. What they share is gathered here rather than written out twice.
 */
abstract class DiagnosticResultService
{
    use ResolvesDateFilters;

    /**
     * The tabs above the list. "available" and "released" are the same tab
     * under the two names the screens give it, and both are accepted either
     * way so the app never has to care which module it is talking to.
     *
     * @var array<int, string>
     */
    public const TABS = ['all', 'available', 'released', 'pending'];

    /**
     * The value the underlying status column holds once a report is out.
     */
    protected const RELEASED = 'Ready';

    public function __construct(
        protected PatientContextService $context,
        protected PatientReportService $reports,
    ) {}

    /**
     * A fresh query over this module's orders, already scoped to the patient.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function newQuery();

    /**
     * The relations a row is presented with.
     *
     * @return array<int, string>
     */
    abstract protected function relations(): array;

    /**
     * Render this module's report for one order.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $order
     * @return string  raw PDF bytes
     */
    abstract protected function renderReport(Model $order): string;

    /**
     * What to call the record when it cannot be found.
     */
    abstract protected function notFoundMessage(): string;

    /**
     * A fresh query over this module's catalogue of tests — the table that
     * patient_visit_lab.test_id / patient_visit_radiology.test_id points at.
     *
     * History is asked for by test rather than by record, so the catalogue is
     * what turns the id in the URL into a test the patient may have taken on
     * any number of visits.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function testCatalogue();

    /**
     * What to call the test when the catalogue has never heard of it and the
     * patient has no record ordered from it either.
     */
    abstract protected function testNotFoundMessage(): string;

    /**
     * The list behind the All / Released / Pending tabs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function index($request)
    {
        $dateFilter = $this->dateFilter($request);

        $records = $this->newQuery()
            ->when(!empty($request['tab']), function ($query) use ($request) {
                $this->applyTab($query, $request['tab']);
            })
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = $request['search_param'];
                $query->where(function ($q) use ($search) {
                    $q->where('test_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('department', 'LIKE', '%' . $search . '%');
                });
            })
            ->when(!empty($request['date']), function ($query) use ($request) {
                $query->whereDate('created_at', Carbon::parse($request['date'])->toDateString());
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->with($this->relations())
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC');

        if (!empty($request['paginate'])) {
            $page = $records->paginate($request['limit'] ?? 15);
            $page->getCollection()->transform(fn($order) => $this->decorate($order));

            return $page;
        }

        return $records->get()->map(fn($order) => $this->decorate($order));
    }

    /**
     * How many records sit behind each tab, for their badges.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'all' => $this->newQuery()->count(),
            'released' => $this->newQuery()->where('status', self::RELEASED)->count(),
            'pending' => $this->newQuery()->where('status', '!=', self::RELEASED)->count(),
        ];
    }

    /**
     * One record of the signed in patient.
     *
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): Model
    {
        $record = $this->newQuery()->with($this->relations())->find($id);

        if (!$record) {
            throw new PatientAppException($this->notFoundMessage(), 404);
        }

        return $this->decorate($record, true);
    }

    /**
     * Every released report this patient holds for one test, behind "View
     * Previous Reports".
     *
     * The id in the URL is the test's id in this module's catalogue
     * (lab_services / radiology_services), not the id of a single order. A
     * patient takes the same test across many visits and each visit writes its
     * own row, so asking by test is what gathers those rows into one history;
     * asking by order id could only ever describe the visit already on screen.
     *
     * Only released records are listed — one still being worked on has no
     * report to look back at. Rows written before test_id was recorded are
     * still found, by the name the catalogue holds for that test, so a
     * patient's older visits do not fall out of their history.
     *
     * @param  int  $testId  id in this module's test catalogue
     * @param  int|null  $exclude  id of the record on screen, left out of its
     *                             own history when the app passes it
     * @return \Illuminate\Support\Collection
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function history($testId, $exclude = null)
    {
        $test = $this->testCatalogue()->find($testId);

        $records = $this->newQuery()
            ->where('status', self::RELEASED)
            ->where(function ($query) use ($testId, $test) {
                $query->where('test_id', $testId);

                // Rows from before test_id was recorded carry only the name the
                // test was ordered under.
                if ($test && !empty($test->name)) {
                    $query->orWhere(function ($q) use ($test) {
                        $q->whereNull('test_id')->where('test_name', $test->name);
                    });
                }
            })
            ->when($exclude, function ($query) use ($exclude) {
                $query->where('id', '!=', $exclude);
            })
            ->with($this->relations())
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        // An unknown test is an error; a known test the patient has simply
        // never taken is an empty history, which the screen shows as such.
        if (!$test && $records->isEmpty()) {
            throw new PatientAppException($this->testNotFoundMessage(), 404);
        }

        return $records->map(fn($order) => $this->decorate($order));
    }

    /**
     * The report as a downloadable PDF response.
     *
     * @param  int  $id
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function download($id)
    {
        $record = $this->show($id);

        if (!$record->is_released) {
            throw new PatientAppException('This report has not been released yet.', 409);
        }

        $pdf = $this->renderReport($record);
        $fileName = $this->reports->fileName($record->test_name);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    /**
     * Attach what the row cannot answer for itself: the hospital it belongs to
     * and, on the detail screen, the document card for its report.
     *
     * The document is only costed on the detail screen. Working out a file's
     * size and page count means rendering it, and rendering one report per row
     * of a list would make the list pay for downloads nobody asked for.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $order
     * @param  bool  $withDocument
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function decorate(Model $order, bool $withDocument = false): Model
    {
        $tenant = $this->context->tenant();

        $order->setAttribute('hospital', [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
            'address' => $tenant->address,
        ]);

        if ($withDocument && $order->is_released) {
            $pdf = $this->renderReport($order);
            $order->setAttribute(
                'document',
                $this->reports->documentMeta($pdf, $this->reports->fileName($order->test_name))
            );
        }

        return $order;
    }

    /**
     * Narrow a query to one of the list's tabs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $tab
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyTab($query, string $tab)
    {
        return match (strtolower($tab)) {
            'available', 'released' => $query->where('status', self::RELEASED),
            'pending' => $query->where('status', '!=', self::RELEASED),
            default => $query,
        };
    }

    /**
     * The patient this module is answering for.
     */
    protected function patientId(): int
    {
        return $this->context->patient()->id;
    }
}
