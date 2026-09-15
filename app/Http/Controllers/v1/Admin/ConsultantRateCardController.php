<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultantRateCard;
use App\Models\Role;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Billing\ConsultantRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConsultantRateCardController extends Controller
{
    public function __construct(private ConsultantRateService $rateService) {}

    /** All consultant rate cards (including the default row). */
    public function index()
    {
        try {
            $cards = ConsultantRateCard::orderByDesc('is_default')->latest()->get();

            return JsonResponser::send(false, 'Rate card(s) found successfully', $cards);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while fetching rate cards.', [], 500, $th);
        }
    }

    /** List tenant consultants (for the doctor dropdown), each with their resolved rate card. */
    public function consultants(Request $request)
    {
        try {
            $tenantUuid = $request->header('X-Tenant-ID');

            $role = Role::where('tenant_id', $tenantUuid)->where('name', 'consultant')->first();
            $userIds = $role
                ? DB::connection('tenant')->table('role_user')->where('role_id', $role->id)->pluck('user_id')
                : collect();

            $consultants = User::on('landlord')
                ->whereIn('id', $userIds)
                ->get(['id', 'fullname', 'first_name', 'last_name', 'email']);

            $cards = ConsultantRateCard::whereIn('user_id', $userIds)->get()->keyBy('user_id');

            $consultants->each(function ($user) use ($cards) {
                $user->rate_card = $cards->get($user->id);
            });

            return JsonResponser::send(false, 'Consultant(s) found successfully', $consultants);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while fetching consultants.', [], 500, $th);
        }
    }

    public function showForUser($userId)
    {
        $card = $this->rateService->forUser((int) $userId) ?? $this->rateService->defaultCard();

        return JsonResponser::send(false, 'Rate card found successfully', $card);
    }

    public function upsertForUser(Request $request, $userId)
    {
        if ($error = $this->validateRate($request)) {
            return $error;
        }

        try {
            $card = $this->rateService->upsertForUser((int) $userId, $request->all());

            return JsonResponser::send(false, 'Consultant rate card saved successfully', $card);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while saving the rate card.', [], 500, $th);
        }
    }

    public function getDefault()
    {
        return JsonResponser::send(false, 'Default rate card', $this->rateService->defaultCard());
    }

    public function setDefault(Request $request)
    {
        if ($error = $this->validateRate($request)) {
            return $error;
        }

        try {
            $card = $this->rateService->setDefault($request->all());

            return JsonResponser::send(false, 'Default rate card saved successfully', $card);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while saving the default rate card.', [], 500, $th);
        }
    }

    private function validateRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_visit_price' => ['required', 'numeric', 'min:0'],
            'returning_price'   => ['required', 'numeric', 'min:0'],
            'markup_type'       => ['nullable', 'in:fixed,percentage'],
            'markup_value'      => ['nullable', 'numeric', 'min:0'],
            'status'            => ['nullable', 'in:Active,Inactive'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        return null;
    }
}
