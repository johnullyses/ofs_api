<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Store;


class DivertedOrderController extends Controller
{
    private $ofs;

    public function __construct() {
        $this->ofs = DB::connection('ofs');
    }

    public function getDivertedOrders(Request $request, $storeid) {

        $startDate = $request->startDate . " 00:00:00";
        $endDate   = $request->endDate . " 23:59:59";
        
        $sql = "SELECT
              `ofs_orders`.`order_date`,
              `ofs_orders`.`order_pin` as order_number,
              `ofs_orders`.`diverted_by_name` as diverted_by,
              `ofs_orders`.`user_name` as created_by,
              `OS1`.`store_name` as orig_store,
              `OS2`.`store_name` as new_store,
              `ODO`.`reason`,
              `ODO`.`remarks`,
              `ODO`.`created_date` as diverted_date
            FROM
              `ofs_diverted_orders` AS ODO
                INNER JOIN `ofs_stores` AS OS1 ON `ODO`.`from_store_id` = `OS1`.`id`
                INNER JOIN `ofs_stores` AS OS2 ON `ODO`.`to_store_id` = `OS2`.`id`
                INNER JOIN `ofs_orders` ON `ODO`.`order_id` = `ofs_orders`.`id`
            WHERE
              `from_store_id` <> 200
              AND `ofs_orders`.`store_id`= $storeid
              AND `ofs_orders`.`order_date` BETWEEN '$startDate' AND '$endDate'
            ORDER BY `ofs_orders`.`order_date` ASC";

        $result = $this->ofs->select($sql);

        return $result;  
    }

}
