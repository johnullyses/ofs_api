<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'ofs_orders';

    protected $connection = 'ofs';

    public $timestamps = false;

    public function order_items()
    {
        return $this->hasMany('App\Models\OrderItem');
    }

    public function customer()
    {
        return $this->belongsTo('App\Models\Customer');
    }

    public function delivery_address()
    {
        return $this->hasOne('App\Models\DeliveryAddress');
    }

    public function store()
    {
        return $this->hasOne('App\\Models\Store','id','store_id');
    }

    public function order_notes()
    {
        return $this->hasMany('App\Models\OrderNote');
    }

    public function delivery_bookings()
    {
        return $this->hasOne('App\Models\DeliveryBooking')->where("status", "!=" , "CANCELLED");
    }



}
