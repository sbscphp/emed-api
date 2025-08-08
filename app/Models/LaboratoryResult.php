<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryResult extends Model
{
    use HasFactory;
    protected $connection = 'tenant';
    protected $guarded = ["id"];
    protected $table = 'lab_test_results';
}
