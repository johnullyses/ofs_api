<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Street extends Model
{
    protected $connection = 'ofs';
    protected $table = 'ofs_street_list';
    public $timestamps = false;
}
