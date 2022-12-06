<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryBooking extends Model
{
    protected $table = 'ofs_delivery_bookings';

    protected $connection = 'ofs';

    public $timestamps = false;
}

?>