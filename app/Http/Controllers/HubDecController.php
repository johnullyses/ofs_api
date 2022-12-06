<?php

namespace App\Http\Controllers;

use App\Models\HoldStore;
use App\Models\HoldLog;
use App\Models\Store;
use App\Models\HubDecBackEnd;
use App\Models\HubDeclaration;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Http\Resources\StoreResource\HubDec as HubDeclarationResource;
use App\Http\Resources\StoreResource\HoldStore as HoldStoreResource;
use App\Http\Resources\StoreResource\StoreOperatingHours as StoreOperatingHours;
use App\Http\Resources\StoreResource\StoreHolidays as StoreHolidays;
use Illuminate\Http\Request;

class HubDecController extends Controller
{
    private $hubDec;

    public function __construct(HubDeclaration $hubDec)
    {
        $this->hubDec = $hubDec;
        $this->getHubDec = DB::connection('ofs');
    }

    public function holdSchedule(Store $store,Request $request){

      $storeHold = Store::with([
              'hold_store'
          ])
          ->find($store->id);

      return new HoldStoreResource($storeHold->hold_store);
    }
    public function gethubDecList(Store $store){
      $HubDeclaration = HubDecBackEnd::select('declaration')->get();
      $declaration=array();
      foreach($HubDeclaration as $dec){
        array_push($declaration,$dec['declaration']);
      }
      return $declaration;
    }
    public function validateStorePin(Store $store, Request $request){

        $valid = false;
        if($request->store_pin == $store->hub_dec_pin){
          $valid = true;
        }
  
        $result = [
          "validated" => $valid
        ];
  
        return $result;
    }

    /**
     * get current hub declaration
     *
     * @return \Illuminate\Http\Response
     */

    public function getCurrentHubDeclaration(Store $store){

      // $hubDec = $this->hubDec
      //             ->where('store_id', $store->id)
      //             ->get()
      //             ->last();
      // return new HubDeclarationResource($hubDec);

      // $user_link_store = $store->id;
      // $sql="SELECT
      //     CASE WHEN `from_time` < NOW() AND `to_time` > NOW()
      //     THEN 'HOLD'
      //     ELSE `ofs_stores`.`promised_time`
      //     END AS promised_time
      //               FROM `ofs_stores`
      //               LEFT JOIN  `ofs_hold_store` ON `ofs_stores`.`id` = `ofs_hold_store`.`store_id`
      //               WHERE `ofs_stores`.`id`= ".$user_link_store."
      //               ORDER BY `ofs_hold_store`.`id` DESC LIMIT 1";

      // $result = $this->getHubDec->select($sql);
     
      // return ['promised_time' => $result[0]->promised_time];

      // return Store::get();

      // die();
      $query=Store::select('promised_time')
      ->where('id',$store->id)
      ->get();
      return ['promised_time' =>$query[0]->promised_time];
      
    }

    /**
     * set hub declaration
     *
     * @return \Illuminate\Http\Response
     */

    public function setHubDeclaration(Store $store, Request $request){
   
      $hubDec = new HubDeclaration();
      $dateTime = date('Y-m-d H:i:s');
      $dateTimePlus60 = date('Y-m-d H:i:s', strtotime('+1 hour'));

      if($request->promised_time == 'HOLD'){
        $holdStore = new HoldStore();
        $holdLog = new HoldLog();

        $holdStore->store_id = $store->id;
        $holdStore->reason = $request->reason;
        $holdStore->remarks = 0;
        $holdStore->recurring = 0;
        $holdStore->from_time = $dateTime;
        $holdStore->to_time = $dateTimePlus60;
        $holdStore->datetime_created = $dateTime;
        // $holdStore->created_by = $request->created_by;
        // $holdStore->user_name = $request->username;

        $holdStore->save();

        $holdLog->store_id = $store->id;
        $holdLog->reason = $request->reason;
        $holdLog->remarks = 0;
        $holdLog->recurring = 0;
        $holdLog->from_time = $dateTime;
        $holdLog->to_time = $dateTimePlus60;
        $holdLog->datetime_created = $dateTime;
        // $holdLog->created_by = $request->created_by;
        // $holdLog->user_name = $request->username;

        $holdLog->save();
      }
      // $hubDec->store_id = $store->id;
      // $hubDec->reason = $request->reason;
      // $hubDec->promised_time = $request->promised_time;
      // $hubDec->authorized_by = $request->authorized_by;
      // $hubDec->created_date = $dateTime;
      // $hubDec->save();
      // $stores = Store::where('id', $store->id)->update(['promised_time' => $request->promised_time]);
      // return new HubDeclarationResource($hubDec);
      $update =DB::table('ofs_hub_declaration')->insert(
        [
           'store_id'   => $store->id,
           'reason'     => $request->reason,
           'promised_time' => $request->promised_time,
           'authorized_by'    => $request->authorized_by,
           'created_date'       => $dateTime,
        ]
      );
      $update =DB::table('ofs_stores')->where('id', $store->id)->update(
        [
           'promised_time' => $request->promised_time,
        ]
      );
      return $update;
    }

    public function getStoreOperatingHours(Store $store, Request $request) {
      $schedule = HoldStore::where("store_id", $store->id)->whereIn("day_of_week", [1,2,3,4,5,6,7])->orderBy('day_of_week', 'asc')->get();
      $holidays = HoldStore::where("store_id", $store->id)->where("recurring", 0)->orderBy('from_time', 'asc')->get();
      return [
        "schedule" => StoreOperatingHours::collection($schedule),
        "holidays" => StoreHolidays::collection($holidays)
      ];
    }

    public function setStoreOperatingHours(Store $store, Request $request) {

      $schedule = (array) $request->schedule;

      HoldStore::where("store_id", $store->id)->whereIn("day_of_week", [1,2,3,4,5,6,7])->delete();
      HoldLog::where("store_id", $store->id)->whereIn("day_of_week", [1,2,3,4,5,6,7])->delete();

       foreach($schedule as $date) {
          $holdStore = new HoldStore();
          $holdLog = new HoldLog();

          $dateTime = date('Y-m-d H:i:s');

          $from_time = "1970-01-01" . " " . $date["end_time"];
          $to_time   = "1970-01-01" . " " . $date["start_time"];

          $holdStore->store_id = $store->id;
          $holdStore->reason = "weekly schedule";
          $holdStore->remarks = 0;
          $holdStore->recurring = 2;
          $holdStore->day_of_week = $date["day"];
          $holdStore->from_time = $from_time;
          $holdStore->to_time = $to_time;
          $holdStore->datetime_created = $dateTime;
          // $holdStore->created_by = $request->created_by;
          // $holdStore->user_name = $request->username;

          $holdStore->save();

          $holdLog->store_id = $store->id;
          $holdLog->reason = "weekly schedule";
          $holdLog->remarks = 0;
          $holdLog->recurring = 2;
          $holdLog->day_of_week = $date["day"];
          $holdLog->from_time = $from_time;
          $holdLog->to_time = $to_time;
          $holdLog->datetime_created = $dateTime;
          // $holdLog->created_by = $request->created_by;
          // $holdLog->user_name = $request->username;

          $holdLog->save();
       }
       
       return 1;
    }

    public function createHoliday(Store $store, Request $request) {
      $holdStore = new HoldStore();
      $holdLog = new HoldLog();

      $dateTime = date('Y-m-d H:i:s');

      $from_time = $request->end_time;
      $to_time   = $request->start_time;

      $holdStore->store_id = $store->id;
      $holdStore->reason = "holiday schedule";
      $holdStore->remarks = 0;
      $holdStore->recurring = 0;
      $holdStore->from_time = $from_time;
      $holdStore->to_time = $to_time;
      $holdStore->datetime_created = $dateTime;
      // $holdStore->created_by = $request->created_by;
      // $holdStore->user_name = $request->username;

      $holdStore->save();

      $holdLog->store_id = $store->id;
      $holdLog->reason = "holiday schedule";
      $holdLog->remarks = 0;
      $holdLog->recurring = 0;
      $holdLog->from_time = $from_time;
      $holdLog->to_time = $to_time;
      $holdLog->datetime_created = $dateTime;
      // $holdLog->created_by = $request->created_by;
      // $holdLog->user_name = $request->username;

      $holdLog->save();

      return 1;
    }

    public function updateHoliday(Store $store, Request $request) {
      $this->createHoliday($store, $request);
      $this->deleteHoliday($store, $request);
      return 1;
    }

    public function deleteHoliday(Store $store, Request $request) {
      HoldStore::where("store_id", $store->id)->where("id", $request->id)->delete();
      HoldLog::where("store_id", $store->id)->where("id", $request->id)->delete();
      return 1;
    }
}
