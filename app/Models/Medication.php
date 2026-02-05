<?php

namespace App\Models;

use Azeemade\BulkUpload\Contracts\BulkUploadable;
use Azeemade\BulkUpload\Concerns\Uploadable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medication extends Model implements BulkUploadable
{
    use HasFactory, Uploadable;
    protected $connection = 'tenant';
    protected $guarded = ['id'];

    protected array $tempMetadata = [];

    /**
     * Receive metadata from the upload request.
     */
    public function setBulkUploadMetadata(array $metadata): void
    {
        $this->tempMetadata = $metadata;
    }

    /**
     * Define validation rules for a single row.
     */
    public function getUploadValidationRules(array $row): array
    {
        return [
            'generic_name' => 'required|string|max:255',
            'medicine_type' => 'required|string|max:255',
            'selling_price' => 'required|numeric|min:0',
            'reg_no' => 'required|string|unique:tenant.medications,reg_no',
            'manufacturer' => 'required|string|max:255',
            'medicine_status'  => 'nullable|in:available,about to expire,out of stock,expired',
            'active_ingredient' => "nullable|string",
            'pharmacy_id' => 'nullable|exists:tenant.pharmacies,id',
            // fields from request that were commented out or not mandatory can be added as nullable if needed
            'brand_name' => 'nullable|string|max:255',
            'medicine_name' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Optional: Provide sample data for the template.
     */
    public function getTemplateSample(): array
    {
        return [
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Panadol',
            'medicine_name' => 'Panadol Extra',
            'medicine_type' => 'Tablet',
            'selling_price' => 15.50,
            'cost_price' => 10.00,
            'reg_no' => 'REG-12345',
            'manufacturer' => 'GSK',
            'medicine_status' => 'available',
            'active_ingredient' => 'Paracetamol 500mg',
            // 'pharmacy_id' => 1,
        ];
    }

    /**
     * Optional: Provide description/options for the template.
     */
    public function getTemplateOptions(): array
    {
        return [
            'generic_name' => 'Generic name of the medication',
            'brand_name' => 'Brand name (optional)',
            'medicine_name' => 'Medicine name (optional)',
            'medicine_type' => 'Type (e.g., Tablet, Syrup)',
            'selling_price' => 'Selling price',
            'cost_price' => 'Cost price (optional)',
            'reg_no' => 'Registration number (must be unique)',
            'manufacturer' => 'Manufacturer name',
            'medicine_status' => 'Status: available, about to expire, out of stock, expired',
            'active_ingredient' => 'Active ingredient',
            // 'pharmacy_id' => 'Pharmacy ID (optional, must exist)',
        ];
    }

    /**
     * Process a single valid row.
     */
    public function processUploadRow(array $row): void
    {
        $data = [
            'generic_name' => $row['generic_name'],
            'brand_name' => $row['brand_name'] ?? null,
            'medicine_name' => $row['medicine_name'] ?? null,
            'medicine_type' => $row['medicine_type'],
            'selling_price' => $row['selling_price'],
            'cost_price' => $row['cost_price'] ?? 0,
            'reg_no' => $row['reg_no'],
            'manufacturer' => $row['manufacturer'],
            'medicine_status' => $row['medicine_status'] ?? 'available',
            'active_ingredient' => $row['active_ingredient'] ?? null,
            // 'pharmacy_id' => $row['pharmacy_id'] ?? null,
            'created_by' => $this->tempMetadata['created_by'] ?? null, // Capture from metadata
            'tenant_id' => $this->tempMetadata['tenant_id'] ?? null,
        ];

        // Remove null values if you want DB defaults to take over, or keep them if columns are nullable
        // For 'created_by' it's important to have it if your schema requires it.

        $this->create($data);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    // public function medicationInventories()
    // {
    //     return $this->hasMany(MedicationInventory::class);
    // }
}
