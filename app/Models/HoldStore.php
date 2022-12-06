<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoldStore extends Model
{
  protected $table = 'ofs_hold_store';

  protected $connection = 'ofs';

  public $timestamps = false;
}
