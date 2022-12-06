<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdbAddress extends Model
{
    protected $table = 'ofs_cdb_address';
    public $timestamps = false;
    protected $connection = 'ofs';
}
