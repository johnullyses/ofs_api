<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderNote extends Model
{
  protected $table = 'ofs_order_notes';

  protected $connection = 'ofs';
}

?>