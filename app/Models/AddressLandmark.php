<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddressLandmark extends Model
{
    protected $table = 'ofs_address_landmarks';
    public $timestamps = false;
    protected $connection = 'ofs';
}
