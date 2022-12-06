<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Resources\OrderResource\OrderMonitoring as OrderMonitoring;
use App\Http\Resources\OrderResource\OrderDetails as OrderDetails;
use App\Http\Resources\ProximityResource\Proximity as ProximityResource;
use Mail;
use App\Mail\SendEmail;
use DB;
use App\Models\Street;
use App\Models\Order;

class StoreAssignmentController extends Controller
{
    private $order;
    private $Street;
    public function __construct(Order $order, Street $Street)
    {
        $this->order = $order;
        $this->Street = $Street;
    }
    public function assign_store(Request $request)
    {
        if (is_null($request) || count($request->all()) <= 0) {
            return response(array("status" => 500, "error" => "JSON Request Empty"), 500);
        }
        $result = [];
        $proximity_radius = 3;
        $province_name  = $request->province_name;
        $city_name      = $request->city_name;
        $full_address   = $request->full_address;
        if (is_null($province_name) || $province_name == NULL || $province_name == "") {
            return response(array("status" => 500, "error" => "province_name missing / empty"), 500);
            exit;
        }
        if (is_null($city_name) || $city_name == NULL || $city_name == "") {
            return response(array("status" => 500, "error" => "city_name missing / empty"), 500);
            exit;
        }
        if (is_null($full_address) || $full_address == NULL || $full_address == "") {
            return response(array("status" => 500, "error" => "full_address missing / empty"), 500);
            exit;
        }
        $query = DB::connection('shared')
            ->table('ofs_street_list as r1')
            ->join('ofs_city_list', function ($query) {
                $query->on('ofs_city_list.city_name', '=', 'r1.city');
            })
            ->join('ofs_provinces', function ($query) {
                $query->on('ofs_provinces.region_id', '=', 'ofs_city_list.region_id');
            })
            ->select(
                "ofs_provinces.province_name",
                DB::raw("r1.*,MATCH(Brgy, Location) AGAINST('$full_address' IN BOOLEAN MODE) AS 'score'"),
                DB::raw("(SELECT DISTINCT r2.Location FROM ofs_street_list as r2 WHERE r2.POINT_X = r1.POINT_X AND r2.POINT_Y = r1.POINT_Y AND r2.Location <> r1.Location LIMIT 1) AS 'intersect_street' ")
            )
            ->where('r1.city', $city_name)
            ->where('ofs_provinces.province_name', $province_name)
            ->whereRaw("MATCH(r1.Brgy,r1.Location) AGAINST('$full_address' IN BOOLEAN MODE)")
            ->orderBy('score', 'DESC')
            ->limit(30)
            ->get();

        $counter = 0;
        foreach ($query as $row) {
            // process atleast top 10 matched address
            if ($counter == 10) {
                $store_data = "";
                break;
            }
            $long = $row->POINT_X;
            $lat = $row->POINT_Y;
            $city = $row->City;
            $brgy = $row->Brgy;
            $location = $row->Location; // formerly "street"
            $intersect_street = $row->intersect_street;
            $relevance = $row->score;

            $city = strtolower($city);
            $store_result = $this->get_store_via_proximity($long, $lat, $city, $proximity_radius);
            if (!empty($store_result)) {

                // assign match address long,lat to each store
                foreach ($store_result as $store) {

                    //$x[] = $store; //['store_name'];

                    $available = $this->check_store_status($store->id);

                    $store_status = $available['store_available'] ? true : false;

                    $is_cashless = $store->is_cashless;

                    $result[] = array(
                        "rta_id" => "",
                        "store_code" => $store->code,
                        "store_id" => $store->id,
                        "store_name" => $store->store_name,
                        "store_address" => $store->address . ", " . $store->city,
                        "city" => $city,
                        "area_subd_district" => $brgy,
                        "street" => $location,
                        "delivery_time" => "",
                        "store_coordinates" => $long . "," . $lat,
                        "coordinates" => $store->x . "," . $store->y,
                        "intersect_street" => $intersect_street,
                        "relevance" => $relevance,
                        "available" => $store_status,
                    );
                }
                break;
            }
            $counter++;
        }
        return response(array("status" => 200, "store_list" => $result), 200);
        exit;
        // return available stores only
        $result_available = $this->filter_by_store_availability($result, 'available', true, true);

        // return mix result available and unavailable
        $result_mix = $this->filter_by_store_availability($result, 'available', true, false);
        return [$result_available, $result_mix];
        // return $query;
    }
    function get_store_via_proximity($long, $lat, $city, $proximity_radius)
    {

        if (is_null($long) || is_null($lat) || is_null($city)) {
            return "";
        }
        $on_foot_proximity_radius = .250;
        $on_foot_proximity_radius = floatval($on_foot_proximity_radius) / 1000;
        $query = DB::table('ofs_stores')
            ->select(
                DB::raw("ofs_stores.*,(ST_DISTANCE(POINT(x, y), POINT($long, $lat)) * 111195) / 1000 AS 'distance',
                CASE WHEN on_foot = 1
                    THEN IF( (ST_DISTANCE(POINT(x, y), POINT($long, $lat)) * 111195) / 1000 > $on_foot_proximity_radius, 1, 1)
                ELSE 1 END AS show_store")
            )
            ->where('is_active', 1)
            ->where('is_template_store', '!=', 1)
            ->having('distance', '<', $proximity_radius)
            ->orderBy('distance', 'ASC')
            ->get();
        return $query;
    }
    function check_store_status($store_id)
    {
        $hold_order_queue = 500;
        $data = array();

        //this will check if store is on hold
        $result = DB::table("ofs_hold_store")
            ->whereRaw("((TIMEDIFF(now(), from_time)>=0 AND TIMEDIFF(now(), to_time)<=0
        AND recurring = 0)
        OR (TIMEDIFF(now(), CONCAT(DATE(now()),' ',TIME(from_time)))>=0 AND TIMEDIFF(now(), CONCAT(DATE(now()),' ',TIME(to_time)))<0
        AND recurring = 1)
        OR (TIMEDIFF(now(), CONCAT(DATE(now()),' ',TIME(from_time)))>=0 AND TIMEDIFF(now(), CONCAT(DATE(now()),' ',TIME(to_time)))<0
        AND DAYOFWEEK(now()) = day_of_week AND recurring = 2))
        AND store_id = '$store_id' ORDER BY from_time DESC limit 1")
            ->get();


        //this will count orders
        $order_count = DB::table("ofs_orders")
            ->where('store_id', $store_id)
            ->whereNotIn("status", [5, 6])
            ->orderBy('is_advance_order', 'DESC')
            ->orderBy('id', 'desc')
            ->count();


        if (is_null($result) || $order_count >= $hold_order_queue) {
            // no hold settings found
            $data['store_available'] = false;
        } else {
            // store on hold status
            $data['store_available'] = true;
        }
        return $data;
    }
    function filter_by_store_availability($array, $index, $value1, $value2)
    {
        // filter the array with the specified category..
        $newarray = "";

        if (is_array($array) && count($array) > 0) {
            foreach (array_keys($array) as $key) {
                $temp[$key] = $array[$key][$index];

                if ($temp[$key] == $value1 || $temp[$key] == $value2) {
                    $newarray[$key] = $array[$key];
                }
            }
        }

        return $newarray;
    }
}
