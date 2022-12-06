<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubDecBackEnd extends Model
{
    protected $table = 'hub_declarations';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $connection = 'ofs';
}

