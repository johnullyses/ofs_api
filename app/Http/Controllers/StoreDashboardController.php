<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HubDeclaration;
use App\Models\Store;
use App\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StoreDashboardController extends Controller
{
    private $order;
    private $hubDec;

    public function __construct(Order $order, HubDeclaration $hubDec)
    {
        $this->order = $order;

        $this->hubDec = $hubDec;
        $this->getHubDec = DB::connection('ofs');
    }

    public function storeDashboard(Request $request, $store_id)
    {
        $stats = array();

        $orderStats = $this->getOrderStatistics($store_id);
        $currentHubDashboard = $this->getHubDashboard($store_id, 1);
        $MTDDashboard = $this->getHubDashboard($store_id, 3);

        $currentHubDec = $this->getHubDeclaration($store_id, 1);
        $MTDHubDec = $this->getHubDeclaration($store_id, 3);

        $currentCancelled = $this->getCanceledOrders($store_id, 1);
        $MTDCancelled = $this->getCanceledOrders($store_id, 3);

        $currentTotalTC = $this->getTotalTC($store_id, 1);
        $MTDTotalTC = $this->getTotalTC($store_id, 3);

        $storeModel = new Store;
        $storeModel->id = $store_id;

        $currentDec = app('App\Http\Controllers\HubDecController')->getCurrentHubDeclaration($storeModel);

        $stats['order_count'] = $orderStats[0]['TOTAL'];
        $stats['order_lapse_count'] = $orderStats[0]['LAPSE'];
        $stats['order_advance_count'] = $orderStats[0]['ADVANCE'];

        $stats['current_hitrate'] = isset($currentHubDashboard[0]['hitrate']) ? number_format($currentHubDashboard[0]['hitrate'], 2) : number_format(0, 2);
        $stats['current_avg_time'] = isset($currentHubDashboard[0]['avg_process_time']) ? $currentHubDashboard[0]['avg_process_time'] : '--:--:--';
        $stats['current_tc'] = isset($currentHubDashboard[0]['tc']) ? $currentHubDashboard[0]['tc'] : 0;
        $stats['current_tc_90_mins'] = isset($currentHubDashboard[0]['tc_90_mins']) ? $currentHubDashboard[0]['tc_90_mins'] : 0;

        $stats['mtd_hitrate'] = isset($MTDDashboard[0]['hitrate']) ? number_format($MTDDashboard[0]['hitrate'], 2) : number_format(0, 2);
        $stats['mtd_avg_time'] = isset($MTDDashboard[0]['avg_process_time']) ? $MTDDashboard[0]['avg_process_time'] : '--:--:--';
        $stats['mtd_tc'] = isset($MTDDashboard[0]['tc']) ? $MTDDashboard[0]['tc'] : 0;
        $stats['mtd_tc_90_mins'] = isset($MTDDashboard[0]['tc_90_mins']) ? $MTDDashboard[0]['tc_90_mins'] : 0;

        $stats['current_hub_dec_120_mins']        = $currentHubDec[0]['declaration_120_mins'];
        $stats['current_hub_dec_180_mins']        = $currentHubDec[0]['declaration_180_mins'];
        $stats['current_hub_dec_hold']           = $currentHubDec[0]['declaration_hold'];
        $stats["current_hub_dec"]                = $currentHubDec[0]["declaration_120_mins"] + $currentHubDec[0]["declaration_180_mins"] + $currentHubDec[0]["declaration_hold"];

        $stats['mtd_hub_dec_120_mins']            = $MTDHubDec[0]['declaration_120_mins'];
        $stats['mtd_hub_dec_180_mins']            = $MTDHubDec[0]['declaration_180_mins'];
        $stats['mtd_hub_dec_hold']               = $MTDHubDec[0]['declaration_hold'];
        $stats["mtd_hub_dec"]                = $MTDHubDec[0]["declaration_120_mins"] + $MTDHubDec[0]["declaration_180_mins"] + $MTDHubDec[0]["declaration_hold"];

        $stats['current_store_assigned_cancels'] = $currentCancelled['store_assigned_cancels'];
        $stats['mtd_store_assigned_cancels']     = $MTDCancelled['store_assigned_cancels'];

        $stats['current_api_count']              = $currentCancelled['api_count'];
        $stats['mtd_api_count']                  = $MTDCancelled['api_count'];

        $stats['current_total_cancels']           = $stats["current_store_assigned_cancels"] + $stats["current_api_count"];
        $stats['mtd_total_cancels']               = $stats["mtd_store_assigned_cancels"] + $stats["mtd_api_count"];

        $stats['current_cancel_tc'] = $currentTotalTC[0]['tc'];
        $stats['mtd_cancel_tc'] = $MTDTotalTC[0]['tc'];

        if ($stats["current_cancel_tc"] != 0) {
            $stats['current_cancel_percents']    = number_format(($stats['current_total_cancels']  / $stats["current_cancel_tc"]) * 100, 2);
        } else {
            $stats['current_cancel_percents'] = number_format(0, 2);
        }

        if ($stats["mtd_cancel_tc"] != 0) {
            $stats['mtd_cancel_percents']        = number_format(($stats['mtd_total_cancels'] / $stats["mtd_cancel_tc"]) * 100, 2);
        } else {
            $stats['mtd_cancel_percents'] = number_format(0, 2);
        }

        return json_encode($stats);
    }


    function getOrderStatistics($store_id)
    {
        $dataOrderStats = $this->order
            ->where('store_id', $store_id)
            ->whereNotIn("status", [5, 6]);
        $dataOrderStats->selectRaw('COUNT(*) AS TOTAL');
        $dataOrderStats->selectRaw('COUNT(CASE WHEN (`is_advance_order` = 1) THEN 1 END) AS ADVANCE');
        $dataOrderStats->selectRaw('COUNT(CASE WHEN (TIMESTAMPDIFF(MINUTE ,`order_date`, NOW()) > 1) THEN 1 END) AS LAPSE');
        $orderStats = $dataOrderStats->get();
        return $orderStats;
    }
    /**
     * get hub dashboard data from store and current or mtd
     * 
     * @param Int $store_id
     * @param Int $mode- 1: current, 2: yesterday, 3: mtd
     * @return array
     */
    function getHubDashboard($store_id, $mode)
    {
        $dataHubDashboard = $this->order
            ->join('ofs_stores', function ($query) {
                $query->on('ofs_stores.id', '=', 'ofs_orders.store_id');
            })
            ->select('ofs_stores.code as store_code', 'ofs_stores.store_name as store_name')
            ->where('ofs_stores.id', $store_id)
            ->when($mode  == 1, function ($query) {
                $query->whereRaw('year = YEAR( CURDATE() )')
                    ->whereRaw('month = MONTH( CURDATE() )')
                    ->whereRaw('day = DAY( CURDATE() )');
            })
            ->when($mode  == 2, function ($query) {
                $query->whereRaw('year = YEAR( DATE_SUB(CURDATE(), INTERVAL 1 DAY) )')
                    ->whereRaw('month = MONTH( DATE_SUB(CURDATE(), INTERVAL 1 DAY) )')
                    ->whereRaw('day = DAY( DATE_SUB(CURDATE(), INTERVAL 1 DAY) )');
            })
            ->when($mode  == 3, function ($query) {
                $query->whereRaw('year = YEAR( CURDATE() )')
                    ->whereRaw('month = MONTH( CURDATE() )');
            })
            ->where('status', 5);
        $dataHubDashboard->selectRaw('COUNT(*) as tc');
        $dataHubDashboard->selectRaw('TRUNCATE(AVG(`ofs_orders`.`total_gross`),2) AS avg_check');
        $dataHubDashboard->selectRaw('COUNT(CASE WHEN (`ofs_orders`.`promised_time` = 90 AND TIMESTAMPDIFF(SECOND, `ofs_orders`.`order_date`, `ofs_orders`.`customer_receive_datetime`) < 5400) THEN 1 END) / COUNT(*) * 100 AS hitrate');
        $dataHubDashboard->selectRaw('COUNT(CASE WHEN (`ofs_orders`.`promised_time` = 90 AND TIMESTAMPDIFF(SECOND, `ofs_orders`.`order_date`, `ofs_orders`.`customer_receive_datetime`) < 5400) THEN 1 END) AS tc_90_mins');
        $dataHubDashboard->selectRaw('TIME_FORMAT(SEC_TO_TIME(AVG(TIMESTAMPDIFF(SECOND, `ofs_orders`.`order_date`, `ofs_orders`.`rider_out_datetime`)) + (AVG(TIMESTAMPDIFF(SECOND, `ofs_orders`.`rider_out_datetime`, `ofs_orders`.`rider_back_datetime`))/2)), \'%H:%i:%s\') AS avg_process_time');

        $db = $dataHubDashboard->groupByRaw('store_id , ofs_stores.code , ofs_stores.store_name')->get();

        return $db;
    }

    /**
     * get current hub declaration data from store and current or mtd
     * 
     * @param Int $store_id
     * @param Int $mode- 1: current, 2: yesterday, 3: mtd
     * @return array
     */
    public function getCurrentHubDeclaration($store_id)
    {

        // $hubDec = $this->hubDec
        //             ->where('store_id', $store->id)
        //             ->get()
        //             ->last();
        // return new HubDeclarationResource($hubDec);

        $sql = "SELECT
          CASE WHEN `from_time` < NOW() AND `to_time` > NOW()
          THEN 'HOLD'
          ELSE `ofs_stores`.`promised_time`
          END AS promised_time
                    FROM `ofs_stores`
                    LEFT JOIN  `ofs_hold_store` ON `ofs_stores`.`id` = `ofs_hold_store`.`store_id`
                    WHERE `ofs_stores`.`id`= " . $store_id . "
                    ORDER BY `ofs_hold_store`.`id` DESC LIMIT 1";

        $result = $this->getHubDec->select($sql);

        return ['promised_time' => $result[0]->promised_time];
    }

    /**
     * get hub declaration data from store and current or mtd
     * 
     * @param Int $store_id
     * @param Int $mode- 1: current, 2: yesterday, 3: mtd
     * @return array
     */
    function getHubDeclaration($store_id, $mode)
    {
        $dataHubDec = $this->hubDec
            ->where('ofs_hub_declaration.store_id', $store_id)
            ->whereRaw('YEAR(`created_date`) = YEAR( CURDATE() )')
            ->whereRaw('MONTH(`created_date`) = MONTH( CURDATE() )')
            ->when($mode  == 1, function ($query) {
                $query->whereRaw('DAY(`created_date`) = DAY( CURDATE() )');
            });

        $dataHubDec->selectRaw('COUNT(CASE WHEN (`ofs_hub_declaration`.`promised_time` = 120) THEN 1 END) AS declaration_120_mins ');
        $dataHubDec->selectRaw('COUNT(CASE WHEN (`ofs_hub_declaration`.`promised_time` = 180) THEN 1 END) AS declaration_180_mins ');
        $dd = $dataHubDec->selectRaw('COUNT(CASE WHEN (`ofs_hub_declaration`.`promised_time` = \'HOLD\') THEN 1 END) AS declaration_hold')->get();
        return $dd;
    }

    /**
     * get cancelled orders data from store and current or mtd
     * 
     * @param Int $store_id
     * @param Int $mode- 1: current, 2: yesterday, 3: mtd
     * @return array
     */
    function getCanceledOrders($store_id, $mode)
    {
        $dc = array();
        $dataCancel = $this->order
            ->select('OS.code', 'OS.store_name', 'order_pin', 'order_date', 'total_gross', 'canceled_datetime', 'OAL.message AS non_aor_message', 'OAL.destination_store', 'CR1.reason AS cancel_primary_reason', 'CR2.reason AS cancel_secondary_reason')
            ->join('ofs_aor_logs as OAL', function ($query) {
                $query->on('ofs_orders.id', '=', 'OAL.order_id');
            })
            ->join('cancel_order_logs as COL', function ($query) {
                $query->on('ofs_orders.id', '=', 'COL.order_id');
            })
            ->join('cancel_reasons AS CR1', function ($query) {
                $query->on('COL.reason_primary_id', '=', 'CR1.id');
            })
            ->join('cancel_reasons AS CR2', function ($query) {
                $query->on('COL.reason_secondary_id', '=', 'CR2.id');
            })
            ->join('ofs_stores as OS', function ($query) {
                $query->on('OAL.destination_store', '=', 'OS.id');
            })
            ->where('status', 6)
            ->where('OAL.destination_store', $store_id)
            ->whereRaw('year = YEAR( CURDATE() )')
            ->whereRaw('month = MONTH( CURDATE() )')
            ->when($mode  == 1, function ($query) {
                $query->whereRaw('day = DAY( CURDATE() )');
            })
            ->where('store_id', $store_id);
        $dc['api_count'] = count($dataCancel->get());

        $dataCancelCount = $this->order
            ->where('status', 6)
            ->where('store_id', $store_id)
            ->whereRaw('year = YEAR( CURDATE() )')
            ->whereRaw('month = MONTH( CURDATE() )')
            ->when($mode  == 1, function ($query) {
                $query->whereRaw('day = DAY( CURDATE() )');
            });
        $dc['store_assigned_cancels'] = count($dataCancelCount->get());
        //echo ($dataCancel->toSql());
        return $dc;
    }

    /**
     * get total tc from store and current or mtd
     * 
     * @param Int $store_id
     * @param Int $mode- 1: current, 2: yesterday, 3: mtd
     * @return array
     */
    function getTotalTC($store_id, $mode)
    {
        $dataTotalTC = $this->order
            ->selectRaw('COUNT(*) as tc')
            ->whereRaw('year = YEAR( CURDATE() )')
            ->whereRaw('month = MONTH( CURDATE() )')
            ->when($mode  == 1, function ($query) {
                $query->whereRaw('day = DAY( CURDATE() )');
            })
            ->where('store_id', $store_id);

        $totalTC = $dataTotalTC->get();
        return $totalTC;
    }

    /**
     * get total orders from store
     * 
     * @param Int $store_id
     * @return array
     */
    function getOrderCount($store_id)
    {
        $dataTotalOrder = $this->order
            ->selectRaw('COUNT(*) as TOTAL')
            ->selectRaw('COUNT(CASE WHEN (`is_advance_order` = 1) THEN 1 END) AS ADVANCE')
            ->selectRaw('COUNT(CASE WHEN (ROUND(TIMESTAMPDIFF(MINUTE, `order_date`, NOW()), 0)) > 0 AS LAPSED')
            ->where('store_id', $store_id)
            ->whereNotIn("status", [5, 6]);

        $totalOrder = $dataTotalOrder->get();
        return $totalOrder;
    }
}
