<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubDeclaration extends Model
{
  protected $table = 'ofs_hub_declaration';

  protected $connection = 'ofs';

  public $timestamps = false;
}
