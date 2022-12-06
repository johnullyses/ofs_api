<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SourceType;
use App\Models\PaymentType;
use App\Http\Resources\ReportResource\CancelledOrder as CancelledOrder;
use Illuminate\Http\Request;
use DB;

class CancelledReportController extends Controller
{
    private $order;

    public function __construct(Order $order, SourceType $sourceType)
    {
        $this->order = $order;
        $this->sourceType = $sourceType;

    }

    public function cancelledOrders(Request $request, $store_id) {
        $cancelledOrders = $this->order
                            ->join('cancel_order_logs', function ($query) {
                                $query->on('cancel_order_logs.order_id', '=', 'ofs_orders.id');
                            })
                            ->whereBetween('order_date', [$request->startDate. " 00:00:00", $request->endDate. " 23:59:59"])
                            ->when($request->source['source_name']  == 'All' ? '' : $request->source, function ($query) use($request) {
                                $query->where('source_id', '=', $request->source['id']);
                            })
                            ->when($request->method['name']  == 'All' ? '' : $request->payment, function ($query) use($request) {
                                $query->where('service_method_id', '=', $request->method['id']);
                            })
                            ->when($request->payment['payment_type']  == 'All' ? '' : $request->payment, function ($query) use($request) {
                                $query->where('payment_id', '=', $request->payment['id']);
                            })
                            ->where('store_id', $store_id)
                            ->where('status', 6)
                            ->get();

        $order = CancelledOrder::collection($cancelledOrders);
        return $order;
    }

    public function orderSources(Request $request)
    {
        $result = [];
        
        $sources = SourceType::where('is_active', 1)->get();
        $payment = PaymentType::get();
        $method = DB::table('ofs_service_method')->get();

        $result['sources'] = $sources;
        $result['payment'] = $payment;
        $result['method'] = $method;

        return $result;        
    }
}
