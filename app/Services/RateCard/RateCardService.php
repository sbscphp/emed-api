<?php

namespace App\Services\RateCard;

use App\Enums\GeneralEnums;
use App\Models\RateCardItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manages the tenant's rate card catalog — the managed price list a billing
 * manager maintains and picks from when raising a manual bill.
 */
class RateCardService
{
    private function tenantId(): ?string
    {
        return app()->bound('currentTenant') ? app('currentTenant')->uuid : null;
    }

    public function all(Request $request)
    {
        $query = RateCardItem::query()->with('serviceUnit');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('service_unit_id')) {
            $query->where('service_unit_id', $request->query('service_unit_id'));
        }

        if ($request->filled('payer_type')) {
            $query->where('payer_type', $request->query('payer_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $query->latest();

        if ($request->boolean('paginate', true)) {
            return $query->paginate($request->query('per_page', 20));
        }

        return $query->get();
    }

    public function create(array $data): RateCardItem
    {
        $data['tenant_id']  = $this->tenantId();
        $data['created_by'] = Auth::id();
        $data['status']     = $data['status'] ?? GeneralEnums::ACTIVE->value;

        return RateCardItem::create($data);
    }

    public function find($id): RateCardItem
    {
        return RateCardItem::with('serviceUnit')->findOrFail($id);
    }

    public function update($id, array $data): RateCardItem
    {
        $item = RateCardItem::findOrFail($id);
        $item->update($data);

        return $item->fresh('serviceUnit');
    }

    public function delete($id): void
    {
        RateCardItem::findOrFail($id)->delete();
    }
}
