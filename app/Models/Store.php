<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
  protected $table = 'ofs_stores';

  protected $connection = 'ofs';

  public $timestamps = false;

  public function hold_store()
  {
      return $this->hasMany('App\Models\HoldStore','store_id','id');
  }

  public function hub_declaration()
  {
      return $this->hasMany('App\Models\HubDeclaration','store_id','id');
  }
}
