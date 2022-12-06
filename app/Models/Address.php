<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
  protected $table = 'ofs_addresses';
  public $timestamps = false;

  protected $connection = 'ofs';


  public function rta()
  {
      return $this->belongsTo('App\Models\Rta','rta_id','id');
  }

  public function landmark()
  {
      return $this->belongsTo('App\Models\Landmark','landmark_id','id');
  }

}
