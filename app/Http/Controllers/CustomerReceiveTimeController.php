<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use DB;

class CustomerReceiveTimeController extends Controller
{
    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function customerReceiveTime(Request $request, $store_id)
    {
        $promisedTime = '';
        if ($request->promisedTime == 'All') {
            $promisedTime = '';
        } else {
            $promisedTime = $request->promisedTime;
        }
        $data = $this->order
                    ->join('ofs_stores', function($query){
                        $query->on('ofs_stores.id', '=', 'ofs_orders.store_id');
                    })
                    ->whereBetween('order_date', [$request->startDate. " 00:00:00", $request->endDate. " 23:59:59"])
                    ->when($request->hourFrom || $request->hourTo, function($query) use($request) {
                        $query->whereBetween('hour', [$request->hourFrom, $request->hourTo]);
                    })
                    ->when($promisedTime, function($query) use($request, $promisedTime) {
                        $query->where('ofs_orders.promised_time', '=', $promisedTime);
                    })
                    ->select('ofs_orders.id as order_id')
                    ->where('store_id', $store_id)
                    ->where('status', 5);
        $data->addSelect('ofs_orders.promised_time');
        $data->addSelect('order_date');
        $data->addSelect('hour');
        // $data->selectRaw('TRUNCATE(SUM(packaging_fee), 2) as packaging_sales');
        $data->selectRaw('TRUNCATE(SUM(total_net), 2) as net_sales');
        $data->selectRaw('TRUNCATE(SUM(total_gross), 2) as total_sales');
        $data->selectRaw('TRUNCATE(AVG(total_gross), 2) as avg_check');
        $data->selectRaw('SEC_TO_TIME(AVG(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime))) as all_avg_time');
        $data->selectRaw('COUNT(ofs_orders.id) as total_orders');
        $data->selectRaw('SEC_TO_TIME(AVG(CASE WHEN TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) < 5400 THEN TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) ELSE 0 END)) as avg_less_90');
        $data->selectRaw('COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) < 5400 )THEN 1 END) as less_90_tc');
        $data->selectRaw('(COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) < 5400 )THEN 1 END) / count(*))*100 as "less_90_"');
        $data->selectRaw('COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) >= 5400 )
        AND (TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) < 7200 )THEN 1 END) as to_90_120_tc');
        $data->selectRaw('(COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) >= 5400 )
        AND (TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) < 7200 )THEN 1 END) / count(*))*100 as "to_90_120_"');
        $data->selectRaw('COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) >= 7200)
        AND (TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) <= 10800) THEN 1 END) as to_120_180');
        $data->selectRaw('(COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) >= 7200)
        AND (TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) <= 10800) THEN 1 END) / count(*))*100 as "to_120_180_"');
        $data->selectRaw('COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) > 10800) THEN 1 END) as over_180_tc');
        $data->selectRaw('(COUNT(CASE WHEN(TIMESTAMPDIFF(SECOND, order_date, customer_receive_datetime) > 10800)THEN 1 END) / count(*))*100 as "over_180_"');
        $data->orderByRaw('ofs_stores.code ASC');
        $crt = $data->groupBy('ofs_orders.hour')->get();

        return response()->json($crt);
    }
}
