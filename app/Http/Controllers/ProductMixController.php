<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mail;
use App\Mail\SendEmail;
use DB;
use App\Models\Product;
class ProductMixController extends Controller
{
    private $product;
    private $reports;
    public function __construct(Product $products,DeliveryPerformance $DeliveryPerformance)
    {
        $this->products = $products;
        //Delivery Performance also points to ofs_reports table so reusing it. 
        $this->reports = $DeliveryPerformance;
    }

    public function getProductMix($store_id)
    {
        $tc_count=DB::connection('ofs')
        ->table('ofs_orders')
        ->where('ofs_orders.store_id', $store_id)
        ->where('ofs_orders.status', 5)
        ->join('ofs_stores', 'ofs_orders.store_id', '=', 'ofs_stores.id')
        ->join('ofs_brands', 'ofs_brands.id', '=', 'ofs_stores.brand_id')
        ->count();

        $query=DB::connection('ofs')
        ->table('ofs_products')
        ->select('ofs_stores.code','ofs_stores.store_name','ofs_products.pos_code','ofs_products.name','ofs_products.category',
        DB::raw('SUM(ofs_order_items.quantity) as quantity'),DB::raw('SUM((ofs_order_items.quantity /'.$tc_count.' ) * 1000) AS upt'),
        DB::raw('SUM(ofs_order_items.item_basic_price * ofs_order_items.quantity) AS net_total'),
        DB::raw('SUM(ofs_order_items.item_price * ofs_order_items.quantity) AS gross_total'))
        ->where('ofs_products.store_id', $store_id)
        ->where('ofs_orders.store_id', $store_id)
        ->join('ofs_order_items', 'ofs_products.pos_code', '=', 'ofs_order_items.child_item_poscode')
        ->join('ofs_orders', 'ofs_orders.id', '=', 'ofs_order_items.order_id')
        ->join('ofs_stores', 'ofs_orders.store_id', '=', 'ofs_stores.id')
        ->groupBy('ofs_products.name')
        ->groupBy('ofs_products.pos_code')
        ->groupBy('ofs_products.category')
        ->orderBy('ofs_products.name', 'ASC')
        ->get();
        foreach($query as $row){
            $row->upt=number_format($row->upt,2);
            $row->net_total=number_format($row->net_total,2);
            $row->gross_total=number_format($row->gross_total,2);
        }
        return json_encode($query);
    }
    public function getProductMixWithFilter($store_id,$start,$end,$source,$product)
    {
        $tc_count=DB::connection('ofs')
        ->table('ofs_orders')
        ->where('ofs_orders.store_id', $store_id)
        ->where('ofs_orders.status', 5)
        ->join('ofs_stores', 'ofs_orders.store_id', '=', 'ofs_stores.id')
        ->join('ofs_brands', 'ofs_brands.id', '=', 'ofs_stores.brand_id');
        if($start != 'ALL'){
            $start = $start." 00:00:00";
            $tc_count->whereDate('ofs_orders.order_date','>=',$start);
        }
        if($end != 'ALL'){
            $end=$end." 23:59:59";
            $tc_count->whereDate('ofs_orders.order_date','<=',$end);
        }
        if($source != 'ALL'){
            $tc_count->where('ofs_orders.source_id',$source);
        }
        $final_tc_count = $tc_count->count();

        $query=DB::connection('ofs')
        ->table('ofs_products')
        ->select('ofs_stores.code','ofs_stores.store_name','ofs_products.pos_code','ofs_products.name','ofs_products.category',
        DB::raw('SUM(ofs_order_items.quantity) as quantity'),
        DB::raw('SUM((ofs_order_items.quantity /'.$final_tc_count.' ) * 1000) AS upt'),
        DB::raw('SUM(ofs_order_items.item_basic_price * ofs_order_items.quantity) AS net_total'),
        DB::raw('SUM(ofs_order_items.item_price * ofs_order_items.quantity) AS gross_total'))
        ->where('ofs_products.store_id', $store_id)
        ->where('ofs_orders.store_id', $store_id)
        ->where('ofs_orders.status', 5)
        ->where('ofs_order_items.is_deleted', 0)
        ->join('ofs_order_items', 'ofs_products.pos_code', '=', 'ofs_order_items.child_item_poscode')
        ->join('ofs_orders', 'ofs_orders.id', '=', 'ofs_order_items.order_id')
        ->join('ofs_stores', 'ofs_orders.store_id', '=', 'ofs_stores.id')
        ->join('ofs_brands', 'ofs_brands.id', '=', 'ofs_stores.brand_id')
        ->when($start != 'ALL', function ($query,$start) {
            $start = $start." 00:00:00";
            $query->whereDate('ofs_orders.order_date','>',$start);
        })
        ->groupBy('ofs_products.name')
        ->groupBy('ofs_products.pos_code')
        ->groupBy('ofs_products.category')
        ->orderBy('ofs_products.name', 'ASC');
        if($start != 'ALL'){
            $start = $start." 00:00:00";
            $query->whereDate('ofs_orders.order_date','>=',$start);
        }
        if($end != 'ALL'){
            $end=$end." 23:59:59";
            $query->whereDate('ofs_orders.order_date','<=',$end);
        }
        if($source != 'ALL'){
            $query->where('ofs_orders.source_id',$source);
        }
        if($product != 'ALL'){
            $query->where('ofs_products.pos_code',$product);
            $query->groupBy('ofs_stores.code');
            $query->groupBy('ofs_stores.store_name');
        }
        $final_query = $query->get();
        foreach($final_query as $row){
            $row->upt=number_format($row->upt,2);
            $row->net_total=number_format($row->net_total,2);
            $row->gross_total=number_format($row->gross_total,2);
        }
        return json_encode($final_query);
    }
    public function getProductsList($store_id)
    {
        $query=DB::connection('ofs')
        ->table('ofs_products')
        ->where('store_id', $store_id)
        ->orderBy('name', 'ASC')
        ->get();
        $query->prepend([
            'pos_code'=>"ALL",
            'name'=>'ALL'
        ]);
        return json_encode($query);
    }
    public function getSourcesList($store_id)
    {
        $query=DB::connection('ofs')
        ->table('ofs_source_type')
        ->where('is_active',1)
        ->get();
        $query->prepend([
            'id'=>"ALL",
            'source_name'=>'ALL'
        ]);
        return json_encode($query);
    }
}
