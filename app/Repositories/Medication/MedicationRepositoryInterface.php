<?php

namespace App\Repositories\Medication;

use Illuminate\Http\Request;

interface MedicationRepositoryInterface
{
    public function all(Request $request);
    public function create(array $data);
    public function find($id);
    public function update($id, array $data);
    public function delete($id);
}
