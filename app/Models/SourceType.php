<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceType extends Model
{
   
    protected $table = 'ofs_source_type';
    protected $connection = 'ofs';
    public $timestamps = false;
}
