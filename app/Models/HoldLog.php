<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoldLog extends Model
{
  protected $table = 'ofs_hold_logs';

  protected $connection = 'ofs';

  public $timestamps = false;
}
