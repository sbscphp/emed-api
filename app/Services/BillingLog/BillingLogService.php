<?php

namespace App\Services\BillingLog;

use App\Models\BillingLog;
use App\Repositories\BillingLog\BillingLogRepositoryInterface;

class BillingLogService
{
    protected $repo;

    public function __construct(BillingLogRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function create(array $data)
    {
        $unitPrice = $data['unit_price'];
        $quantity = $data['quantity'];
        $subTotal = $unitPrice * $quantity;

        $taxAmount = $data['tax_amount'] ?? 0;

        $grandTotal = $subTotal + $taxAmount;

        if ($data['payment_status'] === 'part_paid') {
            if (isset($data['deposit_amount'])) {
                if ($data['deposit_amount'] > $grandTotal) {
                    throw new \Exception('Deposit amount cannot exceed the grand total.');
                }
            } else {
                $data['deposit_amount'] = 0;
            }
        } else {
            $data['deposit_amount'] = null;
        }
        $data['invoice_number'] = $this->generateInvoiceNumber();
        $data['sub_total'] = $subTotal;
        $data['tax_amount'] = $taxAmount;
        $data['grand_total'] = $grandTotal;

        return $this->repo->create($data);
    }


    public function all()
    {
        return $this->repo->all();
    }

    public function find($id)
    {
        return $this->repo->find($id);
    }

    public function update($id, $data)
    {
        $unitPrice = $data['unit_price'];
        $quantity = $data['quantity'];
        $subTotal = $unitPrice * $quantity;

        $taxAmount = $data['tax_amount'] ?? 0;

        $grandTotal = $subTotal + $taxAmount;

        if ($data['payment_status'] === 'part_paid') {
            if (isset($data['deposit_amount'])) {
                if ($data['deposit_amount'] > $grandTotal) {
                    throw new \Exception('Deposit amount cannot exceed the grand total.');
                }
            } else {
                $data['deposit_amount'] = 0;
            }
        } else {
            $data['deposit_amount'] = null;
        }

        $data['sub_total'] = $subTotal;
        $data['tax_amount'] = $taxAmount;
        $data['grand_total'] = $grandTotal;

        return $this->repo->update($id, $data);
    }

    public function delete($id)
    {
        return $this->repo->delete($id);
    }

    private function generateInvoiceNumber()
    {
        $lastBilling = $this->repo->getLatest();

        if ($lastBilling && $lastBilling->invoice_number) {
            $lastNumber = (int) str_replace('INV-', '', $lastBilling->invoice_number);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return 'INV-' . $nextNumber;
    }
    public function getMonthlyRevenue()
    {
        return $this->repo->getMonthlyRevenue();
    }
    public function getPendingPayment()
    {
        return $this->repo->getPendingPayment();
    }
    public function getCompletedPayment()
    {
        return $this->repo->getCompletedPayment();
    }

    public function getFinancialReport($request)
    {
        return $this->repo->getFinancialReport($request);
    }
}
