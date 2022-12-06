<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'order_pin',
        'is_completed',
        'user_id',
        'message',
        'script',
        'is_read'
    ];
    protected $connection = 'ofs';

}
