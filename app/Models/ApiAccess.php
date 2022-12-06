<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiAccess extends Model
{
    protected $table = 'ofs_api_access';
    public $timestamps = false;
    protected $connection = 'ofs';
}
