<?php

namespace App\Services\Patient\Vitals;

use App\Exceptions\PatientAppException;
use App\Models\Triage;
use App\Services\Patient\Concerns\ResolvesDateFilters;
use App\Services\Patient\PatientContextService;
use Carbon\Carbon;

/**
 * Class PatientVitalsService
 *
 * The vitals module of the patient mobile app: the latest set of readings on
 * the home screen and its "Latest Readings" page, the list of every sitting the
 * patient's vitals were taken in, and one of those sittings opened up.
 *
 * Vitals are recorded by the hospital during triage, so this module is read
 * only — a patient looks at their readings, they never write one.
 *
 * The readings themselves are assembled here rather than in the resource,
 * because the same six measurements are read by three different screens and by
 * the dashboard module, and each has to mark a value Normal, High or Low the
 * same way.
 */
class PatientVitalsService
{
    use ResolvesDateFilters;

    /**
     * The relations a reading is presented with.
     *
     * @var array<int, string>
     */
    protected array $relations = ['recordedBy', 'visit.service'];

    public function __construct(protected PatientContextService $context) {}

    /**
     * Every sitting the patient's vitals were taken in, newest first.
     *
     * Filtered by the "Filter date" sheet: a `period` the app names, or a From
     * and To date, both resolved by GeneralHelper::dateFilter the way every
     * other module in the codebase resolves a date range.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function index($request)
    {
        $dateFilter = $this->dateFilter($request);

        $records = $this->baseQuery()
            ->when(!empty($request['date']), function ($query) use ($request) {
                $query->whereDate('created_at', Carbon::parse($request['date'])->toDateString());
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->with($this->relations)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC');

        if (!empty($request['paginate'])) {
            $page = $records->paginate($request['limit'] ?? 15);
            $page->getCollection()->transform(fn(Triage $triage) => $this->decorate($triage));

            return $page;
        }

        return $records->get()->map(fn(Triage $triage) => $this->decorate($triage));
    }

    /**
     * The most recent set of readings, behind the home screen's health snapshot
     * and its "Latest Readings" page.
     *
     * @return \App\Models\Triage|null
     */
    public function latest(): ?Triage
    {
        $record = $this->baseQuery()
            ->with($this->relations)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return $record ? $this->decorate($record) : null;
    }

    /**
     * One sitting of the signed in patient.
     *
     * @param  int  $id
     * @return \App\Models\Triage
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): Triage
    {
        $record = $this->baseQuery()->with($this->relations)->find($id);

        if (!$record) {
            throw new PatientAppException('We could not find that vitals record.', 404);
        }

        return $this->decorate($record);
    }

    /**
     * How many sittings the patient has, and how many of them are recent enough
     * to count as new. Read by the dashboard module for its Vitals tile.
     *
     * @return array{total:int, new:int, last_recorded_at:string|null}
     */
    public function summary(): array
    {
        $latest = $this->baseQuery()->orderBy('created_at', 'DESC')->first(['created_at']);

        return [
            'total' => $this->baseQuery()->count(),
            'new' => $this->baseQuery()
                ->where('created_at', '>=', $this->newSince())
                ->count(),
            'last_recorded_at' => optional($latest)->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Attach the six readings, so every screen renders the same numbers with
     * the same verdicts.
     *
     * @param  \App\Models\Triage  $triage
     * @return \App\Models\Triage
     */
    public function decorate(Triage $triage): Triage
    {
        $triage->setAttribute('readings', $this->readings($triage));
        $triage->setAttribute('hospital', $this->hospital());

        return $triage;
    }

    /**
     * The six measurements a sitting produces, in the order the app lists them.
     *
     * Every entry has the same shape — key, label, value, unit, status — so the
     * app renders one row template rather than six.
     *
     * @param  \App\Models\Triage  $triage
     * @return array<int, array<string, mixed>>
     */
    public function readings(Triage $triage): array
    {
        $bloodPressure = $triage->blood_pressure_reading;

        return [
            [
                'key' => 'blood_pressure',
                'label' => 'Blood Pressure',
                'value' => $this->bloodPressureValue($bloodPressure),
                'unit' => 'mmHg',
                'status' => $this->bloodPressureStatus($bloodPressure),
            ],
            [
                'key' => 'pulse',
                'label' => 'Pulse Rate',
                'value' => $this->numeric($triage->pulse_bpm),
                'unit' => 'bpm',
                'status' => $this->status('pulse', $triage->pulse_bpm),
            ],
            [
                'key' => 'temperature',
                'label' => 'Temperature',
                'value' => $this->numeric($triage->temperature),
                'unit' => '°C',
                'status' => $this->status('temperature', $triage->temperature),
            ],
            [
                'key' => 'blood_sugar',
                'label' => 'Blood Sugar',
                'value' => $this->numeric($triage->sugar_level),
                'unit' => 'mg/dL',
                'status' => $this->status('blood_sugar', $triage->sugar_level),
            ],
            [
                'key' => 'oxygen_saturation',
                'label' => 'Oxygen Saturation',
                'value' => $this->numeric($triage->sp02),
                'unit' => '% SpO2',
                'status' => $this->status('oxygen_saturation', $triage->sp02),
            ],
            [
                // Weight carries no verdict: what is healthy depends on height,
                // age and build, so a range here would be meaningless.
                'key' => 'weight',
                'label' => 'Weight',
                'value' => $this->numeric($triage->weight_kg),
                'unit' => 'Kg',
                'status' => null,
            ],
        ];
    }

    /**
     * A subset of the readings, keyed for a caller that wants a few of them.
     *
     * @param  \App\Models\Triage  $triage
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    public function readingsFor(Triage $triage, array $keys): array
    {
        $readings = collect($this->readings($triage))->keyBy('key');

        return collect($keys)
            ->map(fn($key) => $readings->get($key))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Every vitals query starts here: this hospital, this patient.
     *
     * The hospital half is the database rather than a column. PatientContextService
     * has already made the tenant current, and triages live in that tenant's own
     * database, so the patient id is the whole filter. Deliberately not narrowed
     * by tenant_id as well: that column was added to triages after the fact and
     * is null on rows recorded before it existed, which would quietly hide a
     * patient's older readings.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return Triage::query()->forPatient($this->context->patient()->id);
    }

    /**
     * The hospital a reading was taken at.
     *
     * Triage rows carry no hospital of their own beyond the tenant they live
     * in, which is the hospital the request is already scoped to.
     *
     * @return array<string, mixed>
     */
    protected function hospital(): array
    {
        $tenant = $this->context->tenant();

        return [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
            'address' => $tenant->address,
        ];
    }

    /**
     * The cut off a record has to fall after to count as new.
     */
    protected function newSince(): Carbon
    {
        return Carbon::now()->subDays((int) config('patient_app.records.new_within_days'));
    }

    /**
     * Blood pressure reads as one value, systolic over diastolic.
     *
     * @param  array{systolic: int|null, diastolic: int|null}  $reading
     */
    protected function bloodPressureValue(array $reading): ?string
    {
        if ($reading['systolic'] === null && $reading['diastolic'] === null) {
            return null;
        }

        return ($reading['systolic'] ?? '--') . '/' . ($reading['diastolic'] ?? '--');
    }

    /**
     * Blood pressure is out of range when either half of it is, and the worse of
     * the two verdicts is the one shown.
     *
     * @param  array{systolic: int|null, diastolic: int|null}  $reading
     */
    protected function bloodPressureStatus(array $reading): ?string
    {
        $systolic = $this->status('blood_pressure_systolic', $reading['systolic']);
        $diastolic = $this->status('blood_pressure_diastolic', $reading['diastolic']);

        foreach ([$systolic, $diastolic] as $status) {
            if ($status !== null && $status !== 'Normal') {
                return $status;
            }
        }

        return ($systolic === null && $diastolic === null) ? null : 'Normal';
    }

    /**
     * Mark one value against its reference range.
     *
     * A value with no range configured, or no value at all, gets no verdict
     * rather than a guessed one.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return string|null  Normal, High or Low
     */
    protected function status(string $key, $value): ?string
    {
        $range = config('patient_app.vitals.reference_ranges.' . $key);

        if ($range === null || $value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        if (isset($range['min']) && $value < (float) $range['min']) {
            return 'Low';
        }

        if (isset($range['max']) && $value > (float) $range['max']) {
            return 'High';
        }

        return 'Normal';
    }

    /**
     * Present a stored measurement as a number rather than the padded decimal
     * the column holds, so 70.50 reads as 70.5 and 78.00 as 78.
     *
     * @param  mixed  $value
     * @return float|int|null
     */
    protected function numeric($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return floor($value) === $value ? (int) $value : round($value, 2);
    }
}
