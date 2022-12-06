<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreItem extends Model
{
    protected $table = 'ofs_items';

    protected $connection = 'ofs';

    public $timestamps = false;
}
