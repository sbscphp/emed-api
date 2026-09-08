<?php

namespace App\Services\Patient\Admission;

use App\Exceptions\PatientAppException;
use App\Models\AdmittedPatient;
use App\Services\Patient\Concerns\ResolvesDateFilters;
use App\Services\Patient\PatientContextService;
use Carbon\Carbon;

/**
 * Class PatientAdmissionService
 *
 * The admissions module of the patient mobile app: every stay the patient has
 * had at this hospital, and one of them opened up.
 *
 * Read only. An admission is created, moved and closed off by the ward; the
 * patient reads their own history of it.
 *
 * The list is sorted by the date the patient was admitted rather than by when
 * the row was written, because a ward may enter a stay after the fact and the
 * patient reads the list as a timeline of their own stays.
 */
class PatientAdmissionService
{
    use ResolvesDateFilters;

    /**
     * The relations an admission is presented with.
     *
     * @var array<int, string>
     */
    protected array $relations = ['ward', 'bedSpace', 'department', 'doctor'];

    /**
     * The status an admission carries while the patient is still on the ward.
     */
    protected const ACTIVE = 'Admitted';

    public function __construct(protected PatientContextService $context) {}

    /**
     * The patient's admissions, filtered by status and date.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function index($request)
    {
        $dateFilter = $this->dateFilter($request);

        $records = $this->baseQuery()
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = $request['search_param'];
                $query->where(function ($q) use ($search) {
                    $q->where('admission_no', 'LIKE', '%' . $search . '%')
                        ->orWhere('reason', 'LIKE', '%' . $search . '%')
                        ->orWhereRelation('ward', 'name', 'LIKE', '%' . $search . '%');
                });
            })
            ->when(!empty($request['date']), function ($query) use ($request) {
                $query->whereDate('date_admitted', Carbon::parse($request['date'])->toDateString());
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('date_admitted', $dateFilter);
            })
            ->with($this->relations)
            ->orderBy('date_admitted', 'DESC')
            ->orderBy('id', 'DESC');

        if (!empty($request['paginate'])) {
            $page = $records->paginate($request['limit'] ?? 15);
            $page->getCollection()->transform(fn($record) => $this->decorate($record));

            return $page;
        }

        return $records->get()->map(fn($record) => $this->decorate($record));
    }

    /**
     * How many admissions sit behind each state, for the filter chips.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'total' => $this->baseQuery()->count(),
            'active' => $this->baseQuery()->where('status', self::ACTIVE)->count(),
            'discharged' => $this->baseQuery()->where('status', 'Discharged')->count(),
        ];
    }

    /**
     * The stay the patient is on right now, if there is one.
     *
     * The admissions screen opens on the active admission when one exists and on
     * the history when it does not, so it is answered separately rather than
     * left for the app to find in the list.
     *
     * @return \App\Models\AdmittedPatient|null
     */
    public function active(): ?AdmittedPatient
    {
        $record = $this->baseQuery()
            ->where('status', self::ACTIVE)
            ->with($this->relations)
            ->orderBy('date_admitted', 'DESC')
            ->first();

        return $record ? $this->decorate($record) : null;
    }

    /**
     * One admission of the signed in patient.
     *
     * @param  int  $id
     * @return \App\Models\AdmittedPatient
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): AdmittedPatient
    {
        $record = $this->baseQuery()->with($this->relations)->find($id);

        if (!$record) {
            throw new PatientAppException('We could not find that admission.', 404);
        }

        return $this->decorate($record);
    }

    /**
     * Every admission query starts here: this patient, inside this hospital's
     * own database.
     *
     * Not narrowed by tenant_id as well — the column is nullable on this table
     * and empty on older rows, which would hide a patient's earlier stays.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return AdmittedPatient::query()->where('patient_id', $this->context->patient()->id);
    }

    /**
     * Attach what the row cannot answer for itself: the hospital it was at.
     *
     * @param  \App\Models\AdmittedPatient  $record
     * @return \App\Models\AdmittedPatient
     */
    protected function decorate(AdmittedPatient $record): AdmittedPatient
    {
        $tenant = $this->context->tenant();

        $record->setAttribute('hospital', [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
            'address' => $tenant->address,
        ]);

        return $record;
    }
}
