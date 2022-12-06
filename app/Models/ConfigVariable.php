<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigVariable extends Model
{
    protected $connection = 'ofs';
    protected $table = 'ofs_config_variables';
    public $timestamps = false;
}
