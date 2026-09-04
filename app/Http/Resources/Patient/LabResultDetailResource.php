<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A laboratory result opened from the list.
 *
 * Serves both states of that screen. A released result carries its document
 * card and its result lines; one still being worked on carries neither, and the
 * app shows its "Report Not Available" panel instead — `is_released` is what it
 * switches on.
 */
class LabResultDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospital = $this->hospital ?: [];
        $isReleased = (bool) $this->is_released;
        $results = collect($this->results ?: [])->sortBy('display_order');

        return [
            'id' => $this->id,

            // The test this was ordered from, which /{testId}/history is asked
            // for by. Null on rows written before it was recorded.
            'test_id' => $this->test_id,

            'test_name' => $this->test_name,
            'department' => $this->department,
            'specimen_type' => $this->specimen_type,
            'is_released' => $isReleased,
            'status' => $isReleased ? 'Available' : 'Pending',
            'hospital' => $hospital['name'] ?? null,
            'ordered_by' => $this->doctorName($this->orderedBy),
            'date' => optional($this->created_at)->format('d F Y'),
            'ordered_at' => optional($this->created_at)->toDateTimeString(),
            'released_at' => $isReleased ? optional($results->max('updated_at'))->toDateTimeString() : null,
            'released_on' => $isReleased ? optional($results->max('updated_at'))->format('d F Y') : null,
            'notes' => $this->notes,

            // The card at the top of the screen. Null while the report is still
            // being worked on, which is also when the download endpoint refuses.
            'document' => $isReleased ? ($this->document ?: null) : null,

            // The result lines themselves, so the app can show the numbers as
            // well as offer the PDF.
            'results' => $isReleased
                ? $results->map(fn($result) => [
                    'id' => $result->id,
                    'test' => $result->test,
                    'result' => $result->result,
                    'unit' => $result->unit,
                    'reference_range' => $result->reference_range,
                    'flag' => $result->flag,
                ])->values()
                : [],

            'pending_message' => $isReleased
                ? null
                : 'Your laboratory is still processing this Report',
        ];
    }

    /**
     * Present the ordering doctor the way the app titles them.
     */
    protected function doctorName($doctor): ?string
    {
        if (!$doctor) {
            return null;
        }

        $name = $doctor->fullname ?: trim($doctor->first_name . ' ' . $doctor->last_name);

        if ($name === '') {
            return null;
        }

        return str_starts_with(strtolower($name), 'dr') ? $name : 'Dr. ' . $name;
    }
}
