<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusList extends Model
{
    protected $connection = 'ofs';
    protected $table = 'ofs_status_list';
    public $timestamps = false;
}
