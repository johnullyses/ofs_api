<?php

namespace App\Http\Resources\ProximityResource;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Address;
use App\Models\DeliveryAddress;
use App\Http\Resources\StoreResource\Store as StoreResource;

class Proximity extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      $store = new StoreResource($this->whenLoaded('store'));

      $address = new Address;
      $address_id =  $this->delivery_address->address_id;

      $order_id = $this->id;

      $result = $address->with(['rta','landmark'])
              ->where('id',$address_id)
              ->get()->first();

      $distance = $this->get_distance($order_id);
      $travel_time = $this->get_ett($distance);

      $proximity = $distance . " km (" . $travel_time . " mins approx.)";

      return [
          'distance_km' => $distance,
          'travel_time_mins' => (int)$travel_time,
         
      ];
    }
    private function get_distance($order_id){
      $delivery_address = new DeliveryAddress;

      $result = $delivery_address
            ->join('ofs_orders', 'ofs_orders.id', '=', 'ofs_delivery_address.order_id')
            ->join('ofs_stores', 'ofs_orders.store_id', '=', 'ofs_stores.id')
            ->join('ofs_addresses', 'ofs_delivery_address.address_id', '=', 'ofs_addresses.id')
            ->where('ofs_orders.id',$order_id)
            ->first();

      $store_long = $result->x;
      $store_lat = $result->y;
      $address_long = $result->point_x;
      $address_lat = $result->point_y;

      $distance = $this->calculate_distance(
          $store_lat,
          $store_long,
          $address_lat,
          $address_long
        );

      return $distance;

    }
    /*
    private function get_distance($a_x, $a_y, $b_x, $b_y){

      $pi80 = M_PI / 180;
      $a_x *= $pi80;
      $a_y *= $pi80;
      $b_x *= $pi80;
      $b_y *= $pi80;

      $r  = 6372.797; // mean radius of Earth in km
      $dx = $a_x - $b_x;
      $dy = $a_y - $b_y;

      $a  = sin($dx / 2) * sin($dx / 2) + cos($a_x) * cos($b_x) * sin($dy / 2) * sin($dy / 2);
      $c  = 2 * atan2(sqrt($a), sqrt(1 - $a));
      $km = $r * $c;

      $distance = number_format($km, 2, '.', '');

      return $distance;
    }
    */

    private function get_ett($distance)
    {
        // get estimated travel time
        $travel_time = "";
        $speed_kph   = 40; // given speed in Kph
        $distance_km = $distance; // destination distance (kilometer)

        // convert km->mi and kph->mph
        $speed_mph   = $speed_kph / 1.609344;
        $distance_mi = $distance_km * .6;

        // get initial ETT
        //$ett = round($distance_mi/$speed_mph * 60);
        $ett = $distance_mi * 60 / $speed_mph;

        $ett_2 = $ett * .8; // get 20% of the original ETT
        $ett += $ett_2; // then add it

        // compute hour and minute
        //$Hours = floor($ett / 60);
        //$Minutes = round($ett % 60);
        //$travel_time = $Hours.":".$Minutes;

        $ett = number_format($ett, 2, '.', '');
        $ett = ceil($ett); // convert float to the next higher integer

        return $ett;
    }

    function calculate_distance($store_lat, $store_long, $address_lat, $address_long)
    {
      // calculate distance
  
      $pi80 = M_PI / 180;
      $store_lat *= $pi80;
      $store_long *= $pi80;
      $address_lat *= $pi80;
      $address_long *= $pi80;
  
      $r  = 6372.797; // mean radius of Earth in km
      $dx = $store_lat - $address_lat;
      $dy = $store_long - $address_long;
  
      $a  = sin($dx / 2) * sin($dx / 2) + cos($store_lat) * cos($address_lat) * sin($dy / 2) * sin($dy / 2);
      $c  = 2 * atan2(sqrt($a), sqrt(1 - $a));
      $km = $r * $c;
  
      $distance = number_format($km, 2, '.', '');
  
      return $distance;
  
    }
}
