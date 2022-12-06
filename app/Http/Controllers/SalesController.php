<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Store;


class SalesController extends Controller
{
    private $sales;

    public function __construct()
    {
    $this->sales = DB::connection('ofs');
    }

    public function getSales(Request $request, $store_id){
        
        $storeid = $store_id; 


        $startDate = (string)$request->startDate; 
        $endDate = (string)$request->endDate; 
        $source = $request->source;
        $payment = $request->payment;
        $group_by = ($request->report_group_by == "Daily" ? "day" : "hour");

        $report_type = "";
        $report_payment = "";
         //Source type
        if ($source == "All") {
            $report_type = " AND `ofs_orders`.`source_id` LIKE '%' ";      
        }
        elseif($source == "Phone"){        
            $type_source=1;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";       
        }
        elseif($source == "Web"){       
            $type_source=2;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";      
        }
        elseif($source == "Mobile"){          
            $type_source=3;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";       
        }
        elseif($source == "CTC"){           
            $type_source=4;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";      
        }
        elseif($source == "SMS"){         
            $type_source=5;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";      
        }
        elseif($source == "Mobile Apps"){        
            $type_source=6;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";    
        }
        elseif($source == "fbchatbot"){          
            $type_source=7;
            $report_type = " AND `ofs_orders`.`source_id` = $type_source ";     
        }
             
        //Payment
        if ($payment == "All") {
            $report_payment = " AND `ofs_orders`.`payment_id` LIKE '%' ";
        } else {
            if ($payment == "Cash") {
                $payment = 1;
            }
            elseif ($payment == "Voucher") {
                $payment = 2;
            }
            elseif ($payment == "Paymaya") {
                $payment = 3;
            }
            elseif ($payment == "GCash") {
                $payment = 4;
            }
            elseif ($payment == "Bank Transfer") {
                $payment = 5;
            }
            elseif ($payment == "Credit Card") {
                $payment = 6;
            }
        
            $report_payment = " AND `ofs_orders`.`payment_id` = $payment ";
        }
        

        
        $startDate = $startDate . " 00:00:00";
        $endDate = $endDate . " 23:59:59";
        
        $sql_where = "`ofs_orders`.`order_date` BETWEEN '" . $startDate . "' AND '" . $endDate. "'";


        $sql = "SELECT DATE(`order_date`) AS DateDay, COUNT(*) AS TC
            , `hour`
            , SUM(CASE WHEN `payment_id` = 1 THEN 1 ELSE 0 END) AS CashTC
            , SUM(CASE WHEN `payment_id` = 2 THEN 1 ELSE 0 END) AS VouchersTC
            , SUM(CASE WHEN `payment_id` = 3 THEN 1 ELSE 0 END) AS PaymayaTC
            , SUM(CASE WHEN `payment_id` = 4 THEN 1 ELSE 0 END) AS GcashTC
            , SUM(CASE WHEN `payment_id` = 5 THEN 1 ELSE 0 END) AS BankTransferTC
            , SUM(CASE WHEN `payment_id` = 6 THEN 1 ELSE 0 END) AS CreditCardTC
            , SUM(CASE WHEN `is_advance_order` = 1 THEN 1 ELSE 0 END) AS AdvanceTC
            , SUM(CASE WHEN `is_scd` = 1 THEN 1 ELSE 0 END) AS SCDTC
            , SUM(CASE WHEN `is_pwd` = 1 THEN 1 ELSE 0 END) AS PWDTC
            , SUM(CASE WHEN `is_pwd` = 1 THEN total_discounts ELSE 0 END) AS PWD_DISC
            , SUM(CASE WHEN `is_scd` = 1 THEN total_discounts ELSE 0 END) AS SCD_DISC
            , SUM(`packaging_fee`) AS PackagingSales
            , SUM(`total_net`) AS NetSales
            , SUM(`total_gross`) AS Sales
            , SUM(`total_discounts`) AS Discount
            , SUM(CASE WHEN `promo_discount_code` IS NOT NULL THEN 1 ELSE 0 END) AS PROMOTC
            , SUM(IFNULL(`promo_discount_amount`, 0)) AS PromoDiscount
            , SUM(CASE WHEN `service_method_id` = 1  THEN 1 ELSE 0 END) AS DeliverySalesTC
            , SUM(CASE WHEN `service_method_id` = 2 THEN 1 ELSE 0 END) AS PickupSalesTC
            ,CAST(SUM(`total_gross`) / COUNT(`order_date`)  as decimal(10,2)) as AC
            
            FROM `ofs_orders`
            WHERE
            ".$sql_where." 
            ".$report_type." 
            ".$report_payment." 
            AND `ofs_orders`.`status` = 5
            AND `ofs_orders`.`store_id`= $storeid
            GROUP BY $group_by
            ORDER BY `month`, `day` ASC";

                $result = $this->sales->select($sql);
                return $result;  
    
    }

}
