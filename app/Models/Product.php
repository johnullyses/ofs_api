<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'ofs_products';

    protected $connection = 'ofs';

    public $timestamps = false;
}
