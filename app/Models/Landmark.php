<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Landmark extends Model
{
  protected $table = 'ofs_landmarks';

  protected $connection = 'ofs';
}
?>