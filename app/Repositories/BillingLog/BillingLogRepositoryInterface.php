<?php

namespace App\Repositories\BillingLog;

use Illuminate\Http\Request;

interface BillingLogRepositoryInterface
{
    public function create(array $data);
    public function all($request);
    public function find($id);
    public function update($id, array $data);
    public function delete($id);
    public function getLatest();
    public function getMonthlyRevenue();
    public function getPendingPayment();
    public function getCompletedPayment();
    public function getFinancialReport(Request $request);
}
