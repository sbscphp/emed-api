<?php

namespace App\Services\Patient\Concerns;

use App\Helpers\GeneralHelper;

/**
 * Turns the patient app's date filter sheet into a range to query with.
 *
 * Shared by every module of the app that lists records over time — vitals and
 * appointments today, laboratory, radiology and admissions as their screens
 * arrive — so that a period means the same span of days everywhere, and the
 * same span it means in the admin modules, which resolve theirs through the
 * same helper.
 *
 * The range is always computed by GeneralHelper::dateFilter, and so is always
 * computed in the application timezone. That timezone is the hospital's own
 * wall clock rather than UTC (see config/app.php), which is what keeps "Today"
 * on the day the patient would call today.
 */
trait ResolvesDateFilters
{
    /**
     * Resolve the date range a request is asking for.
     *
     * Named periods ("Today", "7 days", "3 months", ...) and a custom From/To
     * pair both go through GeneralHelper::dateFilter. A start and end date sent
     * on their own are read as a custom range: the app's date sheet posts the
     * two dates its calendar produced without naming a period for them.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<int, \Carbon\Carbon>|bool  false when no range was asked for
     */
    protected function dateFilter($request)
    {
        $period = $request['period'] ?? null;
        $customDate = [];

        if (!empty($request['start_date']) && !empty($request['end_date'])) {
            $period = 'custom date';
            $customDate = [$request['start_date'], $request['end_date']];
        }

        return GeneralHelper::dateFilter($period, $customDate);
    }

    /**
     * The filter a list was actually built with, returned alongside the records.
     *
     * A period is a name on the client and a pair of moments on the server, and
     * only the server knows which. Answering with the range it resolved to means
     * a filter landing on the wrong day is visible in the response itself rather
     * than being guessed at from the records that came back.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function appliedFilter($request): array
    {
        $range = $this->dateFilter($request);

        return [
            'period' => $request['period'] ?? null,
            'date' => $request['date'] ?? null,
            'from' => $range ? $range[0]->toDateTimeString() : null,
            'to' => $range ? $range[1]->toDateTimeString() : null,
            'timezone' => config('app.timezone'),
        ];
    }
}
