<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    protected $table = 'ofs_product_items';

    protected $connection = 'ofs';

    public $timestamps = false;
}
