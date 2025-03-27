<?php

namespace App\Repositories\BillingLog;

use App\Models\BillingLog;

class BillingLogRepository implements BillingLogRepositoryInterface
{
    public function create(array $data)
    {
        return BillingLog::create($data);
    }

    public function all()
    {
        return BillingLog::with(['serviceType', 'serviceUnit', 'patient'])->latest()->paginate(10);
    }

    public function find($id)
    {
        return BillingLog::with(['serviceType', 'serviceUnit', 'patient'])->find($id);
    }

    public function update($id, array $data)
    {
        $log = BillingLog::findOrFail($id);
        $log->update($data);
        return $log;
    }

    public function delete($id)
    {
        return BillingLog::destroy($id);
    }

    public function getLatest()
    {
        return BillingLog::orderBy('id', 'desc')->first();
    }
}
