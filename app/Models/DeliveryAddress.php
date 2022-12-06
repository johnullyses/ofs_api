<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAddress extends Model
{
    protected $table = 'ofs_delivery_address';
    public $timestamps = false;

    protected $connection = 'ofs';

    public function addressDetails()
    {
        return $this->belongsTo('App\Models\Address','address_id','id');
    }

    public function orders()
    {
        return $this->belongsTo('App\Models\Order','order_id','id');
    }

    public function stores()
    {
        return $this->belongsTo('App\Models\Store','store_id','id');
    }

}

