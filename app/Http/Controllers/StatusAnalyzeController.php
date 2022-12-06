<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use DB;

class StatusAnalyzeController extends Controller
{
    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getStatusAnalyze(Request $request, $store_id)
    {
        $data = $this->order
                ->join('ofs_stores', function ($query) {
                    $query->on('ofs_stores.id', '=', 'ofs_orders.store_id');
                })
                ->whereBetween('order_date', [$request->startDate. " 00:00:00", $request->endDate. " 23:59:59"])
                ->where('status', 5)
                ->where('store_id', $store_id);
        $data->addSelect('ofs_stores.code');
        $data->addSelect('ofs_stores.store_name');
        $data->addSelect('ofs_orders.order_number');
        $data->addSelect('ofs_orders.order_pin');
        $data->addSelect('ofs_orders.total_net');
        $data->addSelect('ofs_orders.total_gross');
        $data->selectRaw('TIMESTAMPDIFF(MINUTE, order_date, acknowledged_datetime)-1 as received_to_acknowledge');
        $data->selectRaw('TIMESTAMPDIFF(MINUTE, acknowledged_datetime, rider_assigned_datetime)-5 as acknowledge_to_rider_assigned');
        $data->selectRaw('TIMESTAMPDIFF(MINUTE, rider_assigned_datetime, rider_out_datetime)-5 as rider_assigned_to_rider_out');
        $data->selectRaw('TIMESTAMPDIFF(MINUTE, rider_out_datetime, rider_back_datetime)-20 as rider_out_to_rider_back');
        $data->selectRaw('TIMESTAMPDIFF(MINUTE, rider_back_datetime, closed_datetime) as rider_back_to_close');
        $data->orderByRaw('ofs_stores.store_name ASC');
        $data->orderByRaw('ofs_orders.order_number ASC');
        $st = $data->get();

        return $st;
    }
}
