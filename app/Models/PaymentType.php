<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
   
    protected $table = 'ofs_payment_type';
    public $timestamps = false;
    protected $connection = 'ofs';
}
