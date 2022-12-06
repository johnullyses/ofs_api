<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Resources\OrderResource\OrderMonitoring as OrderMonitoring;
use App\Http\Resources\OrderResource\OrderDetails as OrderDetails;
use App\Http\Resources\ProximityResource\Proximity as ProximityResource;
use Mail;
use App\Mail\SendEmail;
use DB;
use App\Models\Order;
class DeliveryPerformanceController extends Controller
{
    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getReport($store_id)
    {
        $deliveryPerformance=array();
        $orders = $this->order
        ->select(DB::raw('id as TC,order_number,total_net as NetTotal,total_gross as GrossTotal,status_text as Status, order_date as OrderDate'))
        ->where('store_id',$store_id)
        ->where('status',5)
        ->orderBy('received_datetime', 'DESC')
        ->get();
       
        $StorePerformance = $this->order
        ->select(DB::raw('COUNT(CASE WHEN status = 5 THEN id END) AS total_tc,SUM(CASE WHEN status = 5 THEN total_net END) AS net_total,
        SUM(CASE WHEN status = 5 THEN total_gross END) AS gross_total,
        CASE WHEN status = 5 THEN TRUNCATE(AVG(NULLIF(total_gross,0)),2) ELSE 0 END AS avg_check,
        COUNT(CASE WHEN status = 6 THEN 1 END) AS cancel_tc,SUM(CASE WHEN status = 6 THEN total_net ELSE 0 END) AS cancel_basic_total,
        COUNT(
            CASE
                WHEN status = 5 AND
                    ((TIMESTAMPDIFF(SECOND,order_date,rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) < 5400 
                THEN 1
                END) 
                AS less_then_90min,
        COUNT(
            CASE
                WHEN status = 5 AND
                ((TIMESTAMPDIFF(SECOND,order_date, rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) >= 5400 AND ((TIMESTAMPDIFF(SECOND,order_date,rider_out_datetime)) 
                +(TIMESTAMPDIFF(SECOND,rider_out_datetime,rider_back_datetime)/2)) < 7200 
                THEN 1
            END) AS x90_to_120min,
        COUNT(
            CASE
                WHEN status = 5 AND
                ((TIMESTAMPDIFF(SECOND,order_date, rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) >= 7200
                AND ((TIMESTAMPDIFF(SECOND,order_date, rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) < 10800 THEN 1
            END) AS x120_to_180min,
        COUNT(
            CASE
                WHEN `ofs_orders`.`status` = 5 AND
                ((TIMESTAMPDIFF(SECOND,order_date,rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) >= 10800 THEN 1
            END) AS more_than_180min,
        COUNT(
            CASE WHEN status = 5
                  AND (ISNULL(acknowledged_datetime)
                  OR ISNULL(rider_out_datetime)
                  OR ISNULL(rider_back_datetime))
              THEN 1 
            END) as direct_closed
        '))
        ->where('store_id',$store_id)
        ->groupBy('store_id')
        ->get();
        $deliveryPerformance['Orders'] = $orders;
        $deliveryPerformance['StorePerformance'] = $StorePerformance;
        return json_encode($deliveryPerformance);
    }
    public function getReportWithDate($store_id,$start,$end,$promised)
    {
       $start = $start." 00:00:00";
       $end=$end." 23:59:59";
       
        if($promised=="All" || $promised==null  || $promised==false){
            $query=$this->order
            ->select(DB::raw('id as TC,order_number,total_net as NetTotal,total_gross as GrossTotal,status_text as Status, order_date as OrderDate'))
            ->where('store_id',$store_id)
            ->where('status',5)
            ->whereBetween('order_date', [$start, $end])
            ->orderBy('received_datetime', 'DESC')
            ->get();
        }else{
            $query=$this->order
            ->select(DB::raw('id as TC,order_number,total_net as NetTotal,total_gross as GrossTotal,status_text as Status, order_date as OrderDate'))
            ->where('store_id',$store_id)
            ->where('status',5)
            ->where('promised_time',$promised)
            ->whereBetween('order_date', [$start, $end])
            ->orderBy('received_datetime', 'DESC')
            ->get();
        }
        $dbraw='COUNT(CASE WHEN status = 5 THEN id END) AS total_tc,SUM(CASE WHEN status = 5 THEN total_net END) AS net_total,
        SUM(CASE WHEN status = 5 THEN total_gross END) AS gross_total,
        CASE WHEN status = 5 THEN TRUNCATE(AVG(NULLIF(total_gross,0)),2) ELSE 0 END AS avg_check,
        COUNT(CASE WHEN status = 6 THEN 1 END) AS cancel_tc,SUM(CASE WHEN status = 6 THEN total_net ELSE 0 END) AS cancel_basic_total,
        COUNT(
            CASE
                WHEN status = 5 AND
                    ((TIMESTAMPDIFF(SECOND,order_date,rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) < 5400 
                THEN 1
                END) 
                AS less_then_90min,
        COUNT(
            CASE
                WHEN status = 5 AND
                ((TIMESTAMPDIFF(SECOND,order_date, rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) >= 5400 AND ((TIMESTAMPDIFF(SECOND,order_date,rider_out_datetime)) 
                +(TIMESTAMPDIFF(SECOND,rider_out_datetime,rider_back_datetime)/2)) < 7200 
                THEN 1
            END) AS x90_to_120min,
        COUNT(
            CASE
                WHEN status = 5 AND
                ((TIMESTAMPDIFF(SECOND,order_date, rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) >= 7200
                AND ((TIMESTAMPDIFF(SECOND,order_date, rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) < 10800 THEN 1
            END) AS x120_to_180min,
        COUNT(
            CASE
                WHEN `ofs_orders`.`status` = 5 AND
                ((TIMESTAMPDIFF(SECOND,order_date,rider_out_datetime)) + (TIMESTAMPDIFF(SECOND,rider_out_datetime, rider_back_datetime)/2)) >= 10800 THEN 1
            END) AS more_than_180min,
        COUNT(
            CASE WHEN status = 5
                  AND (ISNULL(acknowledged_datetime)
                  OR ISNULL(rider_out_datetime)
                  OR ISNULL(rider_back_datetime))
              THEN 1 
            END) as direct_closed
        ';
        if($promised=="All" || $promised==null || $promised==false){
            $StorePerformance = $this->order
            ->select(DB::raw($dbraw))
            ->where('store_id',$store_id)
            ->whereBetween('order_date', [$start, $end])
            ->groupBy('store_id')
            ->get();
        }else{
            $StorePerformance = $this->order
            ->select(DB::raw($dbraw))
            ->where('store_id',$store_id)
            ->where('promised_time',$promised)
            ->whereBetween('order_date', [$start, $end])
            ->groupBy('store_id')
            ->get();
        }
        $deliveryPerformance['Orders'] = $query;
        $deliveryPerformance['StorePerformance'] = $StorePerformance;
        $deliveryPerformance['start'] = $start;
        $deliveryPerformance['end'] = $end;
        return json_encode($deliveryPerformance);
    }
}
