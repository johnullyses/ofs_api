<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'ofs_order_items';
    public $timestamps = false;

    protected $connection = 'ofs';

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'child_item_poscode', 'pos_code');
    }

    public function scd_food()
    {
        return $this->belongsTo('App\Models\ScdFood', 'order_id', 'order_id')->where('poscode',$this->child_item_poscode);
    }

    public function pwd_food()
    {
        return $this->belongsTo('App\Models\PwdFood', 'order_id', 'order_id')->where('poscode',$this->child_item_poscode);
    }
  

}
