<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityList extends Model
{
    protected $table = 'ofs_city_list';
    protected $connection = 'ofs';
}
