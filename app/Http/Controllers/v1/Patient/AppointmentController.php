<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Appointment\AppointmentIndexRequest;
use App\Http\Requests\Patient\Appointment\AvailabilityRequest;
use App\Http\Requests\Patient\Appointment\BookAppointmentRequest;
use App\Http\Requests\Patient\Appointment\CancelAppointmentRequest;
use App\Http\Requests\Patient\Appointment\SlotRequest;
use App\Http\Requests\Patient\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\Patient\AppointmentDetailResource;
use App\Http\Resources\Patient\AppointmentResource;
use App\Http\Resources\Patient\DepartmentResource;
use App\Http\Resources\Patient\DoctorResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Appointment\AppointmentSlotService;
use App\Services\Patient\Appointment\PatientAppointmentService;
use Illuminate\Http\Request;
use Throwable;

/**
 * The appointment module of the patient mobile app.
 *
 * Two groups of endpoints. The first is the appointment itself — the tabbed
 * list, one appointment, booking, editing, cancelling, checking in and clearing
 * a cancelled one away. The second feeds the booking flow before an appointment
 * exists: consultation types, departments, that department's doctors, the days
 * that doctor is free and the times free on a chosen day.
 *
 * Every route is behind auth:api and the tenant middleware, and the services
 * scope everything again to the patient record the signed in account holds at
 * that hospital.
 */
class AppointmentController extends Controller
{
    public function __construct(
        protected PatientAppointmentService $appointmentService,
        protected AppointmentSlotService $slotService,
    ) {}

    /**
     * GET /v1/patient/appointment
     *
     * The Upcoming / Past / Cancelled list, filtered by `tab`, `status`, a
     * single `date`, or a range given either as a named `period` or as a
     * `start_date`–`end_date` pair.
     */
    public function index(AppointmentIndexRequest $request)
    {
        try {
            $records = $this->appointmentService->index($request);

            $data = [
                'counts' => $this->appointmentService->counts(),
                // The range the period actually resolved to, so a filter landing
                // on the wrong day shows up here rather than having to be
                // inferred from the records that came back.
                'filter' => $this->appointmentService->appliedFilter($request),
                'records' => $request->paginate
                    ? AppointmentResource::collection($records)->response()->getData(true)
                    : AppointmentResource::collection($records),
            ];

            return JsonResponser::send(false, 'Appointments retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/appointment
     *
     * "Confirm Appointment" at the end of the booking flow.
     */
    public function store(BookAppointmentRequest $request)
    {
        try {
            $record = $this->appointmentService->book($request->validated());

            return JsonResponser::send(
                false,
                'Your appointment has been booked successfully.',
                new AppointmentDetailResource($this->appointmentService->decorate($record)),
                201
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/{id}
     */
    public function show($id)
    {
        try {
            $record = $this->appointmentService->show($id);

            return JsonResponser::send(
                false,
                'Appointment retrieved successfully.',
                new AppointmentDetailResource($this->appointmentService->decorate($record)),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/appointment/{id}
     *
     * Reschedule, or correct the reason or the meeting platform.
     */
    public function update(UpdateAppointmentRequest $request, $id)
    {
        try {
            $record = $this->appointmentService->update($id, $request->validated());

            return JsonResponser::send(
                false,
                'Your appointment has been updated successfully.',
                new AppointmentDetailResource($this->appointmentService->decorate($record)),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/appointment/{id}/cancel
     */
    public function cancel(CancelAppointmentRequest $request, $id)
    {
        try {
            $record = $this->appointmentService->cancel($id, $request->input('reason'));

            return JsonResponser::send(
                false,
                'Your appointment has been cancelled.',
                new AppointmentDetailResource($this->appointmentService->decorate($record)),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/appointment/{id}/check-in
     *
     * "Confirm Check in" — the patient saying they have arrived.
     */
    public function checkIn($id)
    {
        try {
            $record = $this->appointmentService->checkIn($id);

            return JsonResponser::send(
                false,
                'You are checked in. The hospital has been notified.',
                new AppointmentDetailResource($this->appointmentService->decorate($record)),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * DELETE /v1/patient/appointment/{id}
     *
     * Clear a cancelled appointment out of the patient's own list.
     */
    public function destroy($id)
    {
        try {
            $this->appointmentService->destroy($id);

            return JsonResponser::send(false, 'The appointment has been removed from your list.', [], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/consultation-types
     *
     * The two cards the booking flow opens on.
     */
    public function consultationTypes()
    {
        try {
            return JsonResponser::send(
                false,
                'Consultation types retrieved successfully.',
                $this->appointmentService->consultationTypes(),
                200
            );
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/departments
     */
    public function departments(Request $request)
    {
        try {
            $records = $this->slotService->departments($request->query('search_param'));

            return JsonResponser::send(
                false,
                'Departments retrieved successfully.',
                DepartmentResource::collection($records),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/departments/{department}/doctors
     */
    public function doctors(Request $request, $department)
    {
        try {
            $records = $this->slotService->doctors($department, $request->query('search_param'));

            return JsonResponser::send(
                false,
                'Doctors retrieved successfully.',
                DoctorResource::collection($records),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/doctors/{doctor}/availability
     *
     * Which days of the calendar can be tapped.
     */
    public function availability(AvailabilityRequest $request, $doctor)
    {
        try {
            $data = $this->slotService->availability(
                $doctor,
                $request->query('from'),
                $request->query('to')
            );

            return JsonResponser::send(false, 'Availability retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/doctors/{doctor}/slots
     *
     * The morning / afternoon / evening times of one day.
     */
    public function slots(SlotRequest $request, $doctor)
    {
        try {
            // Resolved through the patient's own appointments so a stray id
            // cannot be used to unhide somebody else's slot.
            $rescheduling = $request->filled('appointment_id')
                ? $this->appointmentService->show($request->input('appointment_id'))->id
                : null;

            $data = $this->slotService->slots($doctor, $request->input('date'), $rescheduling);

            return JsonResponser::send(false, 'Time slots retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/appointment/meeting-platforms
     *
     * "Choose a meeting platform", for a tele consultation.
     */
    public function meetingPlatforms()
    {
        try {
            return JsonResponser::send(
                false,
                'Meeting platforms retrieved successfully.',
                $this->slotService->meetingPlatforms(),
                200
            );
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
