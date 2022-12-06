<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelOrderLog extends Model
{
    use HasFactory;

    protected $table = 'cancel_order_logs';
    protected $connection = 'ofs';
    public $timestamps = false;
}
