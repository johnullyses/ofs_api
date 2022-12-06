<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BillableSalesController extends Controller
{
    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function billableSales(Request $request, $store_id)
    {
        $data = $this->order
            ->join('ofs_stores', function ($query) {
                $query->on('ofs_stores.id', '=', 'ofs_orders.store_id');
            })
            ->whereBetween('order_date', [$request->startDate . " 00:00:00", $request->endDate . " 23:59:59"])
            ->when($request->source['source_name']  == 'All' ? '' : $request->source, function ($query) use ($request) {
                $query->where('source_id', '=', $request->source['id']);
            })
            ->when($request->method['name']  == 'All' ? '' : $request->payment, function ($query) use ($request) {
                $query->where('service_method_id', '=', $request->method['id']);
            })
            ->when($request->payment['payment_type']  == 'All' ? '' : $request->payment, function ($query) use ($request) {
                $query->where('payment_id', '=', $request->payment['id']);
            })
            ->select('ofs_stores.code as store_code', 'ofs_stores.store_name as store_name')
            ->where('store_id', $store_id)
            ->where('status', 5);
        $data->selectRaw('SUM(CASE WHEN `source_id` = 1 THEN 1 ELSE 0 END) AS voice_tc');
        $data->selectRaw('SUM(CASE WHEN `source_id` <> 1 THEN 1 ELSE 0 END) AS digital_tc');
        $data->selectRaw('SUM(CASE WHEN `user_name` = \'mcdelivery.agent\' THEN 1 ELSE 0 END) AS digital_aor_tc');
        $data->selectRaw('SUM(CASE WHEN (`source_id` <> 1 AND `user_name` <> \'mcdelivery.agent\') THEN 1 ELSE 0 END) AS digital_aa_tc');
        $data->selectRaw('TRUNCATE(SUM(CASE WHEN `source_id` = 1 THEN (`total_net` - IF(`total_discounts` IS NULL, 0, `total_discounts`)) ELSE 0 END), 2) AS voice_billable_sales');
        $data->selectRaw('TRUNCATE(SUM(CASE WHEN `source_id` <> 1 THEN (`total_net` - IF(`total_discounts` IS NULL, 0, `total_discounts`)) ELSE 0 END), 2) AS digital_billable_sales');
        $data->selectRaw('TRUNCATE(SUM(CASE WHEN `user_name` = \'mcdelivery.agent\' THEN (total_net - IF(`total_discounts` IS NULL, 0, `total_discounts`)) ELSE 0 END), 2) AS digital_aor_billable_sales');
        $data->selectRaw('TRUNCATE(SUM(CASE WHEN (`source_id` <> 1 AND `user_name` <> \'mcdelivery.agent\') THEN (`total_net` - IF(`total_discounts` IS NULL, 0, `total_discounts`)) ELSE 0 END), 2) AS digital_aa_billable_sales');
        $billable = $data->groupBy('store_id')->get();

        return $billable;
    }
}
