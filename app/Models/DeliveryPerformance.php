<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPerformance extends Model
{
   
    protected $table = 'ofs_reports';
    protected $connection = 'ofs';
    public $timestamps = false;
}
