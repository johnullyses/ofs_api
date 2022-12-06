<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\DeliveryBooking;
use App\Models\CancelReason;
use App\Models\CancelOrderLog;
use App\Models\DeliveryAddress;
use App\Models\CityList;
use App\Models\SourceType;
use App\Models\PaymentType;
use App\Models\CdbAddress;
use App\Models\Address;
use App\Models\AddressLandmark;
use App\Models\ConfigVariable;
use App\Models\Store;
use App\Models\StatusList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Resources\OrderResource\OrderMonitoring as OrderMonitoring;
use App\Http\Resources\OrderResource\OrderDetails as OrderDetails;
use App\Http\Resources\ProximityResource\Proximity as ProximityResource;
use Mail;
use Auth;
use DB;
use App\Mail\SendEmail;
use Illuminate\Support\Facades\Log;
use DateTime;
use Validator;

class OrderController extends Controller
{
    private $order;
    private $store_name = "";

    public function __construct(Order $order)
    {
        $this->order = $order;
    }


    public function item_availability(Request $request)
    {

        if (is_null($request) || count($request->all()) <= 0) {
            return response(array("status" => 500, "error" => "Request not found"), 500);
        }
        $store_id = $request->store_id;
        $items = $request->items;

        $item_result = array();
        $result = array();

        foreach ($items as $row) {

            $poscode = $row['poscode'];

            // change, not using views anymore [ofs_products_view]
            // list ($name, $gross_price, $basic_price, $is_parent, $product_type) = $this->api_model->get_product_info($row[0]);
            $parameters['sql'] = "SELECT * FROM `ofs_products` WHERE `store_id` = " . $store_id . " AND `pos_code` = '" . $poscode . "' ORDER BY `name` ASC";
            $item_result       = $this->get_store_product_list($parameters);

            // check if we have the product
            if (empty($item_result[0]->pos_code)) {
                $result['status'] = 200;
                $result['result'][] = array(
                    'poscode' => $poscode,
                    'available' => "no" // default to no
                );
            } else {
                // products exist

                $is_enable = $item_result[0]->is_enable; // system wide flag
                $is_active = $item_result[0]->is_active; // store side flag

                $available = ($is_enable && $is_active ? "yes" : "no");
                $result['status'] = 200;
                $result['result'][] = array(
                    'poscode' => $poscode,
                    'available' => $available
                );
            }
        }

        return $result;
    }

    public function order_status(Request $request)
    {

        $status = Order::with(['store'])->where("order_pin", $request->order_pin)->first();
        $response = [];
        if (is_null($status) || is_null($request)) {
            return response(array("status" => 500, "error" => "Order Pin Not found"), 500);
        } else {
            $response['data'] = [
                'order_pin' => $status->order_pin,
                'store_id' => $status->store_id,
                'store_name' => $status->store->store_name,
                'status' => [
                    'id' => $status->status,
                    'text' => $status->status_text,
                    'tracker_url' => $status->track_url,
                ]
            ];
        }
        return $response;
    }

    public function order_place(Request $request)
    {

        //validation
        if (is_null($request->customer_info) || empty($request->customer_info)) {
            Log::error("order_place: Customer info missing/empty.");
            return array("status" => 404, "error" => "Customer info missing/empty.");
        }

        if (is_null($request->delivery_address) || empty($request->delivery_address)) {
            Log::error("order_place: Delivery address missing/empty.");
            return array("status" => 405, "error" => "Delivery address missing/empty.");
        }

        if (is_null($request->order_items) || empty($request->order_items)) {
            Log::error("order_place: Order Items missing/empty.");
            return array("status" => 406, "error" => "Order Items missing/empty.");
        }

        // check gis information
        // if ( is_null($request->delivery_address['gis_info']) || empty($request->delivery_address['gis_info'])) {
        //     Log::error("order_place: gis_info missing/empty.");
        //     return array("status"=> 406, "error"=> "gis_info missing/empty.");
        // }

        // if ( is_null($request->delivery_address['gis_info']['formatted_address']) || empty($request->delivery_address['gis_info']['formatted_address'])) {
        //     Log::error("order_place: formatted_address missing/empty.");
        //     return array("status"=> 406, "error"=> "formatted_address missing/empty.");
        // }

        // if ( is_null($request->delivery_address['gis_info']['location']) || empty($request->delivery_address['gis_info']['location'])) {
        //     Log::error("order_place: location missing/empty.");
        //     return array("status"=> 406, "error"=>"location missing/empty.");
        // }


        // if ( is_null($request->delivery_address['gis_info']['location']['lat']) || empty($request->delivery_address['gis_info']['location']['lat'])) {
        //     Log::error("order_place: lat missing/empty.");
        //     return array("status"=> 406, "error"=>"lat missing/empty.");
        // }

        // if ( is_null($request->delivery_address['gis_info']['location']['lon']) || empty($request->delivery_address['gis_info']['location']['lon'])) {
        //     Log::error("order_place: lon missing/empty.");
        //     return array("status"=>406, "error" => "lon missing/empty.");
        // }
        // check gis end

        $is_valid_data;
        $has_order_items;
        $customer_id = '';
        $order_info = array();
        $discount_details = array(); // discount array details (set to empty)
        $is_free_delivery_charge = false; //by default

        try {

            // main request index
            $customer_info      = $request->customer_info;
            $delivery_address   = $request->delivery_address;
            $order_items        = $request->order_items;
            //$is_advance_order   = $data['contents']['advance_order'];
            $source             = strtolower($request->source);

            // check transaction service delivery method
            $is_pickup = $this->is_pickup($request);
            $promo_discount_amount = 0;
            $promo_discount_total  = 0;

            $save_client_reference_number = false; // flag for saving client reference number CC (cashless)

            $cdb_user_id        = $customer_info["user_id"];
            // $cdb_contact_no_id  = $customer_info["contact_no_id"];
            $firstname          = $customer_info["firstname"];
            $lastname           = $customer_info["lastname"];
            $contact_number     = $customer_info["contact_number"];
            $email_address      = $customer_info["email_address"];
            $send_order_tracker_email = (isset($customer_info["send_order_tracker_email"]) ? $customer_info["send_order_tracker_email"] : 1);
            $payment_type       = $customer_info["payment_type"];
            $change_for         = $customer_info["tendered_amount"];
            $order_remarks      = $customer_info["order_remarks"];

            $cdb_address_id         = $delivery_address["id"];
            $building               = $delivery_address["building"];
            $company                = $delivery_address["company_name"];
            $floor                  = $delivery_address["floor"];
            $area                   = $delivery_address["area"];
            $street                 = $delivery_address["street"];
            $city_id                = $delivery_address["city_id"];
            $landmark               = $delivery_address["landmark"];
            $address_remarks        = $delivery_address["address_remarks"];
            // $promo_discount_code    = $customer_info["promo_discount_code"];
            // $promo_discount_amount  = $customer_info["promo_discount_amount"];

            $woocommerce_post_id    = (isset($customer_info["woocommerce_post_id"]) ? $customer_info["woocommerce_post_id"] : 0);


            // *** validation ***
            // get city region.

            // $city_info = CityList::find($city_id);
            $municipalities = DB::table('ofs_municipalities')->where('municipality_id', $city_id)->get()[0];
            $city_info = collect([
                "id" => $municipalities->municipality_id,
                "region_id" => $municipalities->province_id,
                "city_name" => $municipalities->municipality_name,
                "created_by" => 158,
                "created_datetime" => date('Y-m-d H:i:s'),
                "modified_by" => null,
                "modified_datetime" => null
            ]);
            if (!$city_info) {
                Log::error("Supplied city id cannot be Found.");
                return array("status" => 603, "error" => "Supplied city id cannot be Found.");
            }

            // check contact number lenght
            if ($contact_number == "" || strlen($contact_number) < 7 || strlen($contact_number) > 11) {
                Log::error("Supplied contact number not acceptable.");
                return array("status" => 603, "error" => "Supplied contact number not acceptable.");
            }

            Log::debug((string)$city_info . " city information");

            // generate customer fullname
            $customer_fullname  = trim($firstname) . " " . trim($lastname);


            // get order source id
            $source_id = $this->get_order_source(strtolower($source));

            // get payment id
            $payment_id = $this->get_payment_method(strtolower($payment_type));

            // Full address pattern: Flr/House/Dept, Building, Street, Subd/Area, City
            $address_array = array($company, $floor, $building, $street, $area);
            $full_address  = implode(', ', array_filter($address_array)); // this will not display empty fields.

            // we add boolean "+" for fulltext required word matching
            $param['customer_fullname'] = "+" . trim($firstname) . " +" . trim($lastname);
            $param['customer_phone']    = $contact_number;

            // prepare customer details
            $customer_details = [
                'first_name'      => $firstname,
                'last_name'       => $lastname,
                'contact'         => $contact_number,
                'address'         => ($is_pickup ? "FOR PICK-UP: " . $this->store_name : $full_address),
                'landmarks'       => ($is_pickup ? "FOR PICK-UP: " . $this->store_name : $landmark),
                'address_remarks' => ($is_pickup ? "FOR PICK-UP: " . $this->store_name : $address_remarks),
                'city_id'         => ($is_pickup ? $this->pickup_city : $city_id),
                'region_id'       => ($is_pickup ? $this->pickup_region : $city_info['region_id']),
                'cdb_address_id'  => $cdb_address_id,
                'email_address'   => $email_address
            ];

            Log::debug(json_encode($customer_details) . " customer_details");

            // check client reference number field for cashless paayments
            $payment_type = $customer_info['payment_type'];
            $payment_id_list = [3, 6];
            if (in_array($payment_id, $payment_id_list)) {
                if (is_null($customer_info['client_reference_number']) || empty($customer_info['client_reference_number'])) {
                    $result = [
                        'status' => 400,
                        'error'  => "missing client_reference_number field"
                    ];

                    return $result;
                }
            }

            // we require address [id] for CDB apps for
            // address verification routine
            if (empty($cdb_address_id)) {
                // catch any exceptions and report the problem
                Log::error("customer_delivery_order: address id missing.");
                return array("status" => 605, "error" => "delivery address id missing");
            }

            $cdb_result = $this->cdb_process_customer($customer_details, $is_pickup);
            $customer_id = $cdb_result['customer_id'];
            $address_id  = $cdb_result['address_id'];

            if ($request->store_details) {
                $store_details = $request->store_details;

                $order_info['store_id'] = $store_details["store_id"];
                $order_info['is_pending'] = 0; // order pending flag
                $order_info['brgy'] = $store_details["area_subd_district"];
                $order_info['street'] = $store_details["street"];
                $order_info['landmark'] = "";
                $coordinates = $store_details["coordinates"];
                $latlong = explode(',', $coordinates);
                $order_info['rta_x'] = $latlong[0];
                $order_info['rta_y'] = $latlong[1];

                // assign rta details to customer address
                $data_rta['address_id'] = $address_id;
                $data_rta['rta_id'] = $store_details["rta_id"];
                $data_rta['street'] = $store_details["street"];
                $data_rta['brgy'] = $store_details["area_subd_district"];
                //$this->assign_rta($data_rta); // save rta details to customer address

                $assign_store = Address::find($address_id);
                $assign_store->point_x = $latlong[0];
                $assign_store->point_y = $latlong[1];

                $assign_store->save();
            }

            list(
                $item_poscode,
                $item_qty,
                $product_price,
                $product_basic_price,
                $is_parent_2,
                $gross_total,
                $net_total,
                $quantity_total,
                $item_count
            ) = $this->process_order_items($order_items, $order_info['store_id']);

            // payments rules
            switch ($payment_id) {
                case 1: // cash
                    $this->check_store_details(["store_details" => $request->store_details]); // check store_details field
                    break;

                case 3: // paymaya

                    if (is_null($request->customer_info['client_reference_number']) || empty($request->customer_info['client_reference_number'])) {
                        Log::error("paymaya: client_reference_number: client_reference_number missing/empty.");
                        return array("status" => 406, "error" => "client_reference_number missing/empty.");
                    }

                    $this->check_store_details(["store_details" => $request->store_details]); // check store_details field
                    $save_client_reference_number = true;
                    break;

                case 4: // Gcash

                    // get last the 4 digit of the transaction number
                    if (is_null($request->customer_info['gcash_transaction_id']) || empty($request->customer_info['gcash_transaction_id'])) {
                        Log::error("paymaya: gcash_transaction_id: gcash_transaction_id missing/empty.");
                        return array("status" => 406, "error" => "gcash_transaction_id missing/empty.");
                    }

                    $this->check_store_details(["store_details" => $request->store_details]); // check store_details field
                    //$save_client_reference_number = true;
                    $order_info['gcash_transaction_id'] = $request->customer_info['gcash_transaction_id'];

                    break;

                case 5: // bank transfer

                    $verification_threshold = $this->check_aor_filter['set_amount_verification_threshold'];

                    $co_total_amount = 0;
                    // if(is_array($is_cross_order)) {
                    //     // get co total amount
                    //     $co_total_amount = $is_cross_order['co_bill']['co_total_amount'];
                    // }

                    if ($gross_total >= $verification_threshold || $co_total_amount >= $verification_threshold) {
                        // lock transaction until a payment confirmation
                        $order_info['bank_transfer_payment_confirmed'] = 0;
                    } else {
                        // pre-confirmed the bank transaction
                        $order_info['bank_transfer_payment_confirmed'] = 1;
                    }



                    $this->check_store_details(["store_details" => $request->store_details]); // check store_details field
                    break;

                case 6: // credit card

                    if (is_null($request->customer_info['client_reference_number']) || empty($request->customer_info['client_reference_number'])) {
                        Log::error("paymaya: client_reference_number: client_reference_number missing/empty.");
                        return array("status" => 406, "error" => "client_reference_number missing/empty.");
                    }

                    $this->check_store_details(["store_details" => $request->store_details]); // check store_details field
                    $save_client_reference_number = true;
                    break;

                default:
                    return array("status" => 91, "error" => "Unknown Payment Type");
                    break;
            }

            $order_uuid = $this->uuid();


            //$order_info                                    = array();
            $order_info['customer_id']                     = $customer_id;
            //$order_info['store_id']                        = $this->api_store_id; // mcd_api store
            // $order_info['is_pwd']                          = '';
            // $order_info['pwd_id']                          = '';
            // $order_info['pwd_name']                        = '';
            // $order_info['is_scd']                          = '';
            // $order_info['scd_id']                          = '';
            // $order_info['scd_name']                        = '';
            $order_info['payment_id']                      = $payment_id;
            $order_info['payment_others']                  = '';
            $order_info['source_id']                       = $source_id;
            $order_info['source_others']                   = '';
            $order_info['is_advance_order']                = 0;

            // ***** ADVANCE ORDER ROUTINE
            $is_advance_order = isset($request->advance_order);


            $config = ConfigVariable::All();
            $configTmp = array();
            foreach ($config as $row) {
                $configTmp[$row['key']] = $row['value'];
            }

            $delivery_charge  = $configTmp['delivery_charge'];  // current delivery charge
            $packaging_fee    = $configTmp['packaging_fee']; // packaging_fee
            $promised_time    = $configTmp['promised_time'];

            if ($is_advance_order) {

                $advance = $request->advance_order;
                $advance_date = $advance['date'];
                $advance_time = $advance['time'];

                $advance_date_time = $advance_date . " " . $advance_time;


                $delivery_lead_time = intval($configTmp['advance_order_threshold_delivery']);
                $pickup_lead_time   = intval($configTmp['advance_order_threshold_pickup']);

                if ($is_pickup) {
                    $prep_minutes = $pickup_lead_time;
                } else {
                    $prep_minutes = $delivery_lead_time;
                }

                $date    = date($advance_date_time);
                $newtime = strtotime($date . ' - ' . $prep_minutes . ' minutes');
                $newtime = date('Y-m-d H:i:s', $newtime);

                $order_info['is_advance_order']                = 1;
                $order_info['advance_order_delivery_datetime'] = $newtime;
            } else {
                $order_info['advance_order_delivery_datetime'] = null;
            }


            $order_info['user_id']                         = null; // user_id creator
            $order_info['user_name']                       = ''; // creator user name
            $order_info['excess_vat']                      = 0.00;
            $order_info['total_discount']                  = 0.00;
            $order_info['voucher_amount']                  = 0.00;


            // free delivery charge for pickup
            if ($is_pickup) {
                $is_free_delivery_charge = true;
            }

            // free delivery charge if the store/customer distance is qualified
            if ($store_details['free_delivery_charge'] == "yes") {
                $is_free_delivery_charge = true;
            }


            // compute the packaging fee
            $fee = ($packaging_fee / 100) * $net_total;

            /**
             * check scd/pwd discounts
             */
            $is_scd = $this->is_scd($request);
            $is_pwd = $this->is_pwd($request);

            $discount_total = 0;
            $discount_vat_total = 0;

            if ($is_scd['is_scd']) {

                $format['contents'] = [
                    'store_id' => $request->store_details['store_id'],
                    'items' =>  $request->order_items
                ];

                $discount_result = $this->check_discount($format, true);

                // set data here
                $discount_total = $discount_result['result']['total_discount'];
                $discount_vat_total = $discount_result['result']['total_vat'];
                $order_info['is_scd'] = '1';
                $order_info['scd_id'] = $data['contents']['customer_info']['scd_id'];
                $order_info['scd_name'] = $data['contents']['customer_info']['scd_name'];
                $order_info['excess_vat'] = $discount_result['result']['total_vat'];
                $order_info['total_discount'] = $discount_total;

                $discount_details['is_scd'] = $is_scd['is_scd'];

                // prepare the discount tagging
                $scd_count = null;
                foreach ($discount_result['complete_item_list'] as $key) {
                    $scd_count[] = $key['is_discounted'];
                }

                $discount_details['discount_count'] =  $scd_count;
            } else {
                $order_info['is_scd'] =  null;
                $order_info['scd_id'] = null;
                $order_info['scd_name'] = null;
                $discount_details['is_scd'] = 0;
            }


            // check if have pwd details
            if ($is_pwd['is_pwd']) {

                $format['contents'] = [
                    'store_id' => $request->store_details['store_id'],
                    'items' => $request->order_items
                ];

                $discount_result = $this->check_discount($format, true);

                // set data here
                $discount_total = $discount_result['result']['total_discount'];
                $discount_vat_total = $discount_result['result']['total_vat'];
                $order_info['is_pwd'] = '1';
                $order_info['pwd_id'] = $data['contents']['customer_info']['pwd_id'];
                $order_info['pwd_name'] = $data['contents']['customer_info']['pwd_name'];
                $order_info['excess_vat'] = $discount_result['result']['total_vat'];
                $order_info['total_discount'] = $discount_total;

                $discount_details['is_pwd'] = $is_pwd['is_pwd'];

                // prepare the discount tagging
                $pwd_count = null;
                foreach ($discount_result['complete_item_list'] as $key) {
                    $pwd_count[] = $key['is_discounted'];
                }

                $discount_details['discount_count'] =  $pwd_count;
            } else {
                $order_info['is_pwd'] =  null;
                $order_info['pwd_id'] = null;
                $order_info['pwd_name'] = null;
                $discount_details['is_pwd'] = 0;
            }

            //    // ***** PROMO DISCOUNT ROUTINE !! CURRENTLY WORKS ON BOTH STORES ONLY
            //     $is_promo_code = $this->is_promo_code($request);

            //     if ( $is_promo_code['is_promo_code'] ) {

            //       $promo_code =$request->customer_info['promo_discount_code'];

            //       $promo_discount_total = $this->compute_promo_amount($promo_code, $gross_total);

            //     }


            // ***** PROMISED TIME ROUTINE !!
            if ($is_advance_order) {

                $promised_time = ["promised_time" => $promised_time];
            } else {

                $store_id = $request->store_details['store_id'];
                $promised_time = $this->getCurrentHubDeclaration($store_id);
            }

            /***** DELIVERY CHARGE ROUTINE !! ***************************************************************/

            $is_delivery_charge_sent = array_key_exists('delivery_charge', $customer_info);
            if ($is_delivery_charge_sent) {

                $delivery_charge    = trim($customer_info["delivery_charge"]);
            } else {

                $tentative_total    = ($gross_total - $discount_total - $discount_vat_total - $promo_discount_total);

                $delivery_charge    = $this->compute_delivery_charge($tentative_total);
            }

            if ($is_free_delivery_charge == true) {

                $delivery_charge = 0;
            }

            //$order_info['is_pwd']                          = '';
            //$order_info['pwd_id']                          = '';
            //$order_info['pwd_name']                        = '';
            $order_info['order_no']                        = $request->order_no;
            $order_info['delivery_charge']                 = $delivery_charge;
            $order_info['total_quantity']                  = $quantity_total;
            $order_info['total_net']                       = $net_total;
            $order_info['total_w_vat']                     = $gross_total;
            $order_info['total_gross']                     = ($gross_total - $discount_total - $discount_vat_total - $promo_discount_total) + $fee + $delivery_charge;
            $order_info['tendered_amount']                 = ($change_for == "" ? $order_info['total_gross'] : $change_for);
            $order_info['change_amount']                   = ($change_for == "" ? 0 : $change_for - $order_info['total_gross']);
            $order_info['order_remarks']                   = $order_remarks;
            $order_info['woocommerce_post_id']             = $woocommerce_post_id;
            $order_info['address_id']                      = $address_id; // used address id of the customer
            $order_info['city_id']                         = $city_info['id']; // used city id
            $order_info['order_uuid']                      = $order_uuid; // order uuid identifier
            //$order_info['order_pin']                       = $order_pin; // 6 digit pin
            $order_info['promised_delivery_time']          = $promised_time['promised_time'];
            $order_info['service_method_id']               = ($is_pickup ? 2 : 1);
            $order_info['packaging_fee']                   = $fee;
            // $order_info['promo_discount_amount']           = $promo_discount_total;
            // $order_info['promo_discount_code']             = $promo_discount_code;

            // save partial order information and will return the order number/order id generated
            list($order_id, $order_no, $store_code, $order_datetime_created) = $this->save_customer_orders($order_info);

            // check if we have successfully created the order id
            if (empty($order_id)) {
                Log::error("OFS was unable to create an order id.");
                return array("status" => 606, "error" => "OFS was unable to create an order id.");
            }

            //////////////////////////////////////////////////////////////////
            // [*] SAVE ORDER ITEMS
            //////////////////////////////////////////////////////////////////
            //$discount_details = array(); // discount array details (set to empty)

            $item_result = $this->save_customer_order_items(
                200,
                $item_count,
                $order_id,
                $item_poscode,
                $product_basic_price,
                $product_price,
                $item_qty,
                $is_parent_2,
                $discount_details
            );

            if (!$item_result) {
                Log::error("OFS was unable to save order items");
                return array("status" => 607, "error" => "OFS was unable to save order items.");
            }

            //////////////////////////////////////////////////////////////////
            // [*] SAVE DELIVERY ADDRESS
            //////////////////////////////////////////////////////////////////
            $save_delivery_address                              = array();
            $save_delivery_address['order_id']                  = $order_id;
            $save_delivery_address['address_id']                = $address_id;
            $save_delivery_address['contact_number']            = $contact_number;
            $save_delivery_address['area_district_subdivision'] = $area;
            $save_delivery_address['street']                    = $street;
            $save_delivery_address['city_name']                 = ($is_pickup ? $this->pickup_city : $city_info['id']);
            $save_delivery_address['region']                    = ($is_pickup ? $this->pickup_region : $city_info['region_id']);
            $save_delivery_address['customer_address']          = ($is_pickup ? $pickup_address : $full_address);
            $save_delivery_address['remarks']                   = ($is_pickup ? $pickup_address : $address_remarks);

            // save customer delivery address
            $address_result = $this->save_delivery_address($save_delivery_address);

            return [
                "data" => [
                    'status' => 200,
                    'order_id' => $order_no,
                    // 'primary' => $is_cross_order['co_primary_store'],
                    'received' => '',
                    'instore' => '',
                    'intransit' => '',
                    'delivered' => '',
                    'canceled' => '',
                    // 'remarks' => $aor_flag
                ]
            ];
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $result['status']  = 703;
            $result['error'] = "Unable to process order.";
            return $result;
        }
    }

    function save_delivery_address($address)
    {
        /*
        |--------------------------------------------------------------------------
        | Save customer delivery address
        |--------------------------------------------------------------------------
        */

        $delivery_address = new DeliveryAddress;

        $datetime_created           = date('Y-m-d H:i:s'); // get date and time of filing

        $delivery_address->order_id             = $address['order_id'];
        $delivery_address->address_id           = $address['address_id'];
        $delivery_address->contact_number       = $address['contact_number'];
        $delivery_address->area_subd_district   = $address['area_district_subdivision'];
        $delivery_address->street               = $address['street'];
        $delivery_address->city_municipality    = $address['city_name'];
        $delivery_address->address              = $address['customer_address'];
        $delivery_address->region               = $address['region'];
        $delivery_address->remarks              = $address['remarks'];
        $delivery_address->barangay             = "";
        $delivery_address->province             = "";
        $delivery_address->country              = 0;

        $delivery_address->save();

        return $delivery_address;
    }

    function save_customer_orders($customer_information)
    {
        /*
        |--------------------------------------------------------------------------
        | Saving the customer order details..
        |--------------------------------------------------------------------------
        | --------------------------------------------------------------------------
        | get store order_counter increase it by 1 then update it
        | then assemble order_number
        | --------------------------------------------------------------------------
        */

        // generated order number format: yyyymmdd-store_code-counter
        $order_number = $customer_information['order_no']; //date('Ymd')."-".$store_code."-".$order_counter;

        // get date and time of filing
        $datetime_created = date('Y-m-d H:i:s');
        // date of order
        $order_date = ($customer_information['is_advance_order'] == 1 ? $customer_information['advance_order_delivery_datetime'] : $datetime_created);

        // custom date indexes
        $o_OrderDate = new DateTime($order_date);
        $o_year      = date_format($o_OrderDate, 'Y');
        $o_month     = date_format($o_OrderDate, 'n');
        $o_day       = date_format($o_OrderDate, 'j');
        $o_hour      = date_format($o_OrderDate, 'H');
        $o_day_name  = date_format($o_OrderDate, 'l');
        $o_week      = date_format($o_OrderDate, 'W');


        if ($customer_information['is_pending'] == "1") {
            // if pending set status to pending.
            $data['status']       = 7; // (pending status flag)
            $data['pending_date'] = $datetime_created;
        } else {
            $data['status'] = 1; // normal (receive)
        }

        // get address coordinates
        //$coordinate = $this->get_address_xy($customer_information['address_id']);

        // get city name
        // $city_name = CityList::find($customer_information['city_id'])->city_name;
        $city_name = DB::table('ofs_municipalities')->where('municipality_id', $customer_information['city_id'])->get()[0]->municipality_name;

        $store_code = Store::find($customer_information['store_id'])->code;

        $order = new Order;

        $order->order_number = $order_number;
        $order->order_date   = $order_date;

        $order->year  = $o_year;
        $order->month = $o_month;
        $order->day   = $o_day;
        $order->hour  = $o_hour;
        $order->week  = $o_week;

        $order->created_datetime                = $datetime_created;
        $order->customer_id                     = $customer_information['customer_id'];
        $order->store_id                        = $customer_information['store_id'];
        $order->store_code                      = $store_code;

        $order->is_pwd                          = $customer_information['is_pwd'];
        $order->pwd_id                          = $customer_information['pwd_id'];
        $order->pwd_name                        = $customer_information['pwd_name'];

        $order->is_scd                          = $customer_information['is_scd'];
        $order->scd_id                          = $customer_information['scd_id'];
        $order->scd_name                        = $customer_information['scd_name'];
        $order->excess_vat                      = (array_key_exists('excess_vat', $customer_information) ? $customer_information['excess_vat'] : 0);

        $order->payment_id                      = $customer_information['payment_id'];
        $order->payment_text                    = PaymentType::find($customer_information['payment_id'])->payment_type;
        $order->payment_others                  = $customer_information['payment_others'];
        $order->source_id                       = $customer_information['source_id'];
        $order->source_text                     = SourceType::find($customer_information['source_id'])->source_name;
        $order->source_others                   = $customer_information['source_others'];
        $order->is_advance_order                = $customer_information['is_advance_order'];
        $order->advance_order_delivery_datetime = $customer_information['advance_order_delivery_datetime'];
        $order->promised_time                   = $customer_information['promised_delivery_time'];

        $order->user_id   = $customer_information['user_id'];
        $order->user_name = $customer_information['user_name'];

        $order->total_quantity  = $customer_information['total_quantity'];
        $order->total_w_vat     = $customer_information['total_w_vat'];

        $order->packaging_fee   = $customer_information['packaging_fee'];
        $order->delivery_charge = $customer_information['delivery_charge'];
        $order->total_net       = $customer_information['total_net'];
        $order->total_discounts = $customer_information['total_discount'];
        $order->voucher_amount  = $customer_information['voucher_amount'];
        $order->change_amount   = $customer_information['change_amount'];
        $order->tendered_amount = $customer_information['tendered_amount'];
        $order->total_gross     = $customer_information['total_gross'];
        $order->order_remarks   = $customer_information['order_remarks'];
        $order->woocommerce_post_id    = $customer_information['woocommerce_post_id'];
        // $order->promo_discount_amount  = $customer_information['promo_discount_amount'];
        // $order->promo_discount_code    = $customer_information['promo_discount_code'];

        $order->rta_x     = $customer_information['rta_x'];
        $order->rta_y     = $customer_information['rta_y'];
        $order->city_name = $city_name;

        $order->order_uuid = $customer_information['order_uuid'];
        $order->order_pin  = $order_number; //$customer_information['order_pin'];

        $order->brgy      = $customer_information['brgy'];
        $order->street      = $customer_information['street'];
        $order->landmark    = $customer_information['landmark'];
        $order->status_text = StatusList::find($data['status'])->status_name;
        $order->service_method_id = $customer_information['service_method_id'];
        $order->store_id_for_menu = $customer_information['store_id'];
        $order->packaging_fee = $customer_information['packaging_fee'];
        $order->gcash_transaction_id = (isset($customer_information['gcash_transaction_id']) ? $customer_information['gcash_transaction_id'] : null);
        $order->bank_transfer_payment_confirmed = (isset($customer_information['bank_transfer_payment_confirmed']) ? $customer_information['bank_transfer_payment_confirmed'] : null);



        $order->save();
        $order_id = $order->id;
        $order_no = $order_number;

        $param = array($order_id, $order_no, $store_code, $datetime_created);

        return $param;
    }

    function save_customer_order_items(
        $user_id,
        $total_item_count,
        $order_id,
        $pos_code,
        $product_basic_price,
        $product_price,
        $product_quantity,
        $is_parent,
        $discount_details
    ) {

        $datetime_created = date('Y-m-d H:i:s');

        // loop to all of the selected items...
        for ($i = 0; $i < $total_item_count; $i++) {
            // detect if item is parent if true
            // assign it as the parent poscode
            if ($is_parent[$i] == 'y') {
                $parent_poscode = $pos_code[$i];
            }

            $order_item = new OrderItem;
            $order_item->user_id = $user_id;
            $order_item->order_id = $order_id;
            $order_item->parent_item_poscode = $parent_poscode;
            $order_item->child_item_poscode = $pos_code[$i];
            $order_item->item_basic_price = $product_basic_price[$i];
            $order_item->item_price = $product_price[$i];
            $order_item->quantity = $product_quantity[$i];
            $order_item->remarks = '';
            $order_item->datetime_created =  $datetime_created;

            $order_item->save();

            // save discounted items for scd/pwd
            if ($discount_details['is_scd'] == 1 || $discount_details['is_pwd'] == 1) {
                $discount_sql .= "(" . $order_id . "," .
                    $parent_poscode . "," .
                    $pos_code[$i] . "," .
                    $discount_details['scd_count'][$i] . "),";
            }
        }


        if ($discount_details['is_scd'] == 1 || $discount_details['is_pwd'] == 1) {
            // save discounted scd items
            $this->save_scd_food($discount_sql);
        }

        return true;
    }

    function save_scd_food($insert_sql)
    {

        $sql = "INSERT INTO ofs_scd_food
              (`order_id`, `parent_poscode`, `poscode`, `scd_count`) VALUES ";
        $sql .= $insert_sql;

        $sql = substr($sql, 0, -1);
        $q   = DB::insert($sql);
    }


    function compute_delivery_charge($total_amount)
    {
        $config = ConfigVariable::All();
        $configTmp = array();
        foreach ($config as $row) {
            $configTmp[$row['key']] = $row['value'];
        }

        // get delivery charge from config
        $config_delivery_charge  = $configTmp['delivery_charge'];

        //get delivery ceiling
        $config_delivery_ceiling  = $configTmp['delivery_ceiling'];

        /* calculate delivery charge based on ceiling (4000), delivery charge (80) CONFIGURABLE
           below 4000 = 80 / above 4000 = 160 / above 8000 = 240 / above 10000 = 320 */

        //calculate how many instances of delivery based on the logic above
        $delivery_instances = ceil($total_amount / $config_delivery_ceiling);

        //multiply base delivery charge on the number of instances
        $delivery_charge = $config_delivery_charge * $delivery_instances;

        return $delivery_charge;
    }

    function getCurrentHubDeclaration($storeID)
    {


        $user_link_store = $storeID;
        $sql = "SELECT
            CASE WHEN `from_time` < NOW() AND `to_time` > NOW()
            THEN 'HOLD'
            ELSE `ofs_stores`.`promised_time`
            END AS promised_time
                      FROM `ofs_stores`
                      LEFT JOIN  `ofs_hold_store` ON `ofs_stores`.`id` = `ofs_hold_store`.`store_id`
                      WHERE `ofs_stores`.`id`= " . $user_link_store . "
                      ORDER BY `ofs_hold_store`.`id` DESC LIMIT 1";

        $result = DB::select($sql);

        return ["promised_time" => $result[0]->promised_time];
    }

    // function compute_promo_amount($code, $total_amount)
    // {
    //     $promo_code = $code;

    //     $promo_details = $this->store_model->get_promo_codes($promo_code);
    //     $promo_discount_total = 0;

    //     if(is_array($promo_details)){
    //       if($promo_details['method'] == 1){ // flat amount e.g. 100
    //         $value = $promo_details['value'];

    //         $promo_discount_total = $value; //100 peso to be subtracted
    //       } else if($promo_details['method'] == 2){ //percent e.g. 50%
    //         $value = $promo_details['value'];

    //         $promo_discount_total = ($total_amount / 100) * $value; //if gross total is 1000, 500 peso to be subtracted
    //       }
    //       return $promo_discount_total;
    //     } else {
    //       return $promo_discount_total;
    //     }
    // }

    function is_promo_code($data)
    {

        if (!isset($data->customer_info['promo_discount_code'])) {
            $data['is_promo_code'] = false;
            $data['message'] = "";
            return $data;
        }

        if (is_null($data->customer_info['promo_discount_code']) || empty(trim($data->customer_info['promo_discount_code']))) {
            $data['is_promo_code'] = false;
            $data['message'] = "";
            return $data;
        }

        return ['is_promo_code' => true, 'message' => "Promo Code Active"];
    }

    function check_discount($data, $show_complete_list = false)
    {
        //echo json_encode($data); die();
        // get no. of scd/pwd IDs

        // checking here.....
        // check the main wrapper
        if (is_null($data->store_id) || empty($data->store_id)) {
            Log::error("store_id missing/empty.");
            return ["status" => 801, "error" => "store_id missing/empty."];
        }

        if (is_null($data->items) || empty(element('items', $data->items))) {
            Log::error("items missing/empty.");
            return ["status" => 802, "error" => "items missing/empty."];
        }

        // start check order items wrapper
        $required_indexes = array(
            "poscode" => "poscode",
            "qty" => "qty"
        );

        $missing = array();
        $items = $contents['items'];
        foreach ($items as $item) {
            foreach ($required_indexes as $required_index => $value) {
                if (!array_key_exists($required_index, $item)) {
                    $missing[] = array('error' => 'Missing ' . $value);
                }
            }
        }

        if (!empty($missing)) {
            $result = array(
                'status' => 803,
                'error'  => $missing
            );
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        }
        // end check order items wrapper

        // Processing
        $store_id = $contents['store_id'];
        $items = $contents['items'];

        $item_result = array();
        $result = array();

        foreach ($items as $row) {

            $poscode = $row['poscode'];
            $quantity = $row['qty'];

            $parameters['sql'] = "SELECT * FROM `ofs_products` WHERE `store_id` = " . $store_id . " AND `pos_code` = '" . $poscode . "' ORDER BY `name` ASC";
            $item_result = $this->get_store_product_list($parameters);

            $discountable = $item_result[0]->discountable;

            // we will only process discountable products
            // non-discountable will be excluded
            if ($discountable) {
                $format = [
                    'store_id' => $item_result[0]->store_id,
                    'name' => $item_result[0]->name,
                    'poscode' => $item_result[0]->pos_code,
                    'price' => $item_result[0]->gross_price,
                    'is_active' => $item_result[0]->is_active,
                    'category' => $item_result[0]->category,
                    'discountable' => $item_result[0]->discountable,
                    'quantity' => $quantity
                ];
                $result[] = $format;
            }
        }

        // get the highest price per category
        $filtered[] = $this->filter_discounted_items($result, 'category', 'MAIN');
        $filtered[] = $this->filter_discounted_items($result, 'category', 'SIDES');
        $filtered[] = $this->filter_discounted_items($result, 'category', 'DRINKS');
        $filtered[] = $this->filter_discounted_items($result, 'category', 'DESSERTS');
        $filtered = array_filter($filtered); // we remove empty categories

        // now compute the discount
        $discounted_items = $this->compute_discount($filtered);


        /**
         * map scd tagging
         */
        $map_discounted_items = null;
        $complete_item_list = null;

        foreach ($result as $row) {
            //  map and search discounted/non-discounted item
            $map_discounted_items = array_search($row['poscode'], array_column($discounted_items['items'], 'poscode'));

            // tag matching items from the discounted items then tag it
            // this will use later for saving to the scd table
            if ($map_discounted_items !== false) {
                $row['is_discounted'] = '1';
                $complete_item_list[] = $row;
            } else {
                $row['is_discounted'] = '0';
                $complete_item_list[] = $row;
            }
        }

        $result_set = [
            'status' => 200,
            'result' => $discounted_items,
        ];

        if ($show_complete_list) {
            $result_set['complete_item_list'] = $complete_item_list;
        }

        return $result_set;
    }

    function filter_discounted_items($array, $index, $my_value)
    {
        // filter the array with the specified category..
        $items_array = null;
        $max = null;
        $result = null;

        // get item base on category
        if (is_array($array) && count($array) > 0) {
            foreach (array_keys($array) as $key => $value) {
                $temp[$key] = $array[$key][$index];

                if ($temp[$key] == $my_value) {
                    $items_array[$key] = $array[$key];
                }
            }
        }

        // get the highest price in the category
        foreach ($items_array as $key) {
            if ($max === null || $key['price'] > $max) {
                $result = $key;
                $max = $key['price'];
            }
        }
        return $result;
    }

    function is_scd($data)
    {

        if (!isset($data->customer_info['scd_id']) && !isset($data->customer_info['scd_name'])) {
            $data['is_scd'] = false;
            $data['message'] = "";
            return $data;
        }

        if (is_null($data->customer_info['scd_id']) || empty(trim($data->customer_info['scd_id']))) {
            $data['is_scd'] = false;
            $data['message'] = "";
            return $data;
        }

        if (is_null($data->customer_info['scd_name']) || empty(trim($data->customer_info['scd_name']))) {
            $data['is_scd'] = false;
            $data['message'] = "";
            return $data;
        }

        return ['is_scd' => true, 'message' => "Senior Citizen ID Found."];
    }

    function is_pwd($data)
    {
        if (!isset($data->customer_info['pwd_id']) && !isset($data->customer_info['pwd_id'])) {
            $data['pwd_id'] = false;
            $data['message'] = "";
            return $data;
        }

        if (is_null($data->customer_info['pwd_id']) || empty(trim($data->customer_info['pwd_id']))) {
            $data['is_pwd'] = false;
            $data['message'] = "";
            return $data;
        }

        if (is_null($data->customer_info['pwd_name']) || empty(trim($data->customer_info['pwd_name']))) {
            $data['is_pwd'] = false;
            $data['message'] = "";
            return $data;
        }

        return ['is_pwd' => true, 'message' => "PWD ID Found."];
    }

    function uuid()
    {
        // full hash
        // The field names refer to RFC 4122 section 4.1.2
        return sprintf(
            '%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
            mt_rand(0, 65535),
            mt_rand(0, 65535), // 32 bits for "time_low"
            mt_rand(0, 65535), // 16 bits for "time_mid"
            mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
            bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
            // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
            // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
            // 8 bits for "clk_seq_low"
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535) // 48 bits for "node" 
        );
    }


    function check_store_details($data)
    {

        if (array_key_exists('store_details', $data)) {

            // required fields
            $indexes = array(
                "area_subd_district" => "area_subd_district",
                "city" => "city",
                "coordinates" => "coordinates",
                "delivery_time" => "delivery_time",
                "intersect_street" => "intersect_street",
                "relevance" => "relevance",
                "rta_id" => "rta_id",
                "store_code" => "store_code",
                "store_id" => "store_id",
                "store_name" => "store_name",
                "street" => "street",
                "store_address" => "store_address",
                "brand_code" => "brand_code",
                "approximate_distance" => "approximate_distance",
                "free_delivery_charge" => "free_delivery_charge",
                "can_deliver" => "can_deliver",
                "can_pickup" => "can_pickup"
            );

            $missing = array();
            $contents = $data['store_details'];


            foreach ($indexes as $key => $value) {
                if (!array_key_exists($key, $contents)) {
                    // assemble missing keys
                    $missing[] = array('error' => 'Missing ' . $value);
                }
            }

            // check if we have missing fields
            if (!empty($missing)) {

                $result = array(
                    'status' => 400,
                    'error'  => $missing
                );

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result);
                die();
            }
        } else {
            $result = array(
                'status' => 400,
                'error'  => "missing store_details field"
            );

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result);
            die();
        }

        return true;
    }

    function process_order_items($order_items, $store_id)
    {
        /**
         * this code assumes that the items are ordered by:
         * PARENT - CHILD
         *
         */

        try {

            $i              = 0;
            $j              = 0;
            $gross_total    = 0;
            $net_total      = 0;
            $quantity_total = 0;
            $item_count     = 0;
            $item_sub_qty   = 0;
            $count_regular  = 0;
            $count_upgrade  = 0;

            // $this->debugging($item_string, "clean item array");


            // =========================================
            // CONVERT $ITEM_STRING INTO AN ARRAY
            // =========================================
            foreach ($order_items as $row) {

                $poscode    = $row['poscode'];
                $qty        = $row['qty'];

                // change, not using views anymore [ofs_products_view]
                $parameters['sql'] = "SELECT * FROM `ofs_products` WHERE `store_id` = " . $store_id . " AND `pos_code` = '" . $poscode . "' ORDER BY `name` ASC";
                $result            = $this->get_store_product_list($parameters);

                // check if we have the product
                if (empty($result[0]->pos_code)) {
                    Log::error("Poscode: " . $poscode . ", Name: " . $row['name'] . ", Not Found.");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(array("status" => 608, "error" => "Poscode: " . $poscode . ", Name: " . $row['name'] . ", Not Found."));
                    die();
                }

                $name         = $result[0]->name;
                $product_type = $result[0]->product_type;
                $gross_price  = $row['price'];
                $basic_price  = $row['price'];
                $is_parent    = ($product_type == 5 ? "y" : "n");

                if ($is_parent == "y") {
                    $parent_poscode = trim($poscode);
                    // for parent items
                    $id_tag = 1;
                } else {
                    // for child items (will use current parent_poscode)
                    $id_tag = 2;
                }

                // convert info to arrays
                $item_poscode[$i]        = trim($poscode);
                $item_name[$i]           = $name;
                $item_qty[$i]            = trim($qty);
                $product_price[$i]       = $gross_price;
                $product_basic_price[$i] = $basic_price;

                $product_type_tag[$i] = $product_type; // product type tag id
                $is_parent_2[$i]      = $is_parent;  // tag parent poscode);
                $ref_id[$i]           = $id_tag; // product reference id tag
                $parent_child_tag[$i] = $parent_poscode . "-" . $item_poscode[$i]; // parent and child poscode

                // build array of row items
                $combine[] = array(
                    'parent_child_tag'    => $parent_child_tag[$i],
                    'item_poscode'        => $item_poscode[$i],
                    'item_name'           => $item_name[$i],
                    'item_qty'            => $item_qty[$i],
                    'product_price'       => $product_price[$i],
                    'product_basic_price' => $product_basic_price[$i],
                    'product_type_tag'    => $product_type_tag[$i],
                    'is_parent_2'         => $is_parent_2[$i],
                    'parent_poscode_tag'  => $parent_poscode,
                    'ref_id'              => $ref_id[$i]
                );

                $i += 1;
            }
            /**
             * Now we filter unique products (parent and child)to a separate array
             * then we also separate duplicate parent and child products.
             */

            $temp_array = [];
            $duplicate_parent = [];
            $duplicate_child = [];

            foreach ($combine as &$v) {
                if (!isset($temp_array[$v['parent_child_tag']])) {
                    // get unique products
                    $temp_array[$v['parent_child_tag']] = &$v;
                } else {
                    // get duplicate parent
                    if ($v['is_parent_2'] == 'y') {
                        $duplicate_parent[] = ['parent_child_tag' => $v['parent_child_tag'], 'item_qty' => $v['item_qty']];
                    }

                    // get duplicate child
                    if ($v['is_parent_2'] == 'n') {
                        $duplicate_child[] = ['parent_child_tag' => $v['parent_child_tag'], 'item_qty' => $v['item_qty']];
                    }
                }
            }

            // Unique parent-child products
            $no_dup = array_values($temp_array);
            // Filter Unique Parent products
            $parent_items = $this->filter_by_value($no_dup, 'is_parent_2', 'y');

            // ====================================================
            // CONSTRUCT INITIAL COMPOSITION
            //
            //  - We loop through unique parent products
            //  - and get its [parent_poscode_tag] to filter the  $no_dup array for products
            //  - Using  the result from that, we filter duplicate parent and child, sum them then
            //  - update the unique product array with matching tag.
            // ====================================================
            foreach ($parent_items as $row) {

                // get composition
                $initial_result = $this->filter_by_value($no_dup, 'parent_poscode_tag', $row['parent_poscode_tag']);


                //****** SORT ITEM COMPOSITION ******
                usort($initial_result, function ($a, $b) {
                    return $a['ref_id'] - $b['ref_id'];
                });

                // sum PARENT product qty from the duplicate parent  with the matching 'parent_child_tag'
                foreach ($initial_result as &$frow) {
                    if ($frow['is_parent_2'] == 'y') {
                        if (is_array($duplicate_parent)) {
                            foreach ($duplicate_parent as $dup_parent) {
                                if ($dup_parent['parent_child_tag'] == $frow['parent_child_tag']) {
                                    $frow['item_qty'] += $dup_parent['item_qty'];
                                }
                            }
                        }
                    }
                }

                // sum CHILD product qty from the duplicate child with the matching 'parent_child_tag'
                foreach ($initial_result as &$frow) {
                    if ($frow['is_parent_2'] == 'n') {
                        if (is_array($duplicate_child)) {
                            foreach ($duplicate_child as $dup_child) {
                                if ($dup_child['parent_child_tag'] == $frow['parent_child_tag']) {
                                    $frow['item_qty'] += $dup_child['item_qty'];
                                }
                            }
                        }
                    }
                }

                // BUILD INITIAL ITEM COMPOSITION
                $initial_composition[] = $initial_result;
            }


            // ====================================================
            // NOW WE BUILD THE FINAL COMPOSTION
            // ====================================================
            foreach ($initial_composition as $arr_value) {
                foreach ($arr_value as $value) {

                    // convert info to arrays
                    $f_item_poscode[$j]        = $value['item_poscode'];
                    $f_item_name[$j]           = $value['item_name'];
                    $f_item_qty[$j]            = $value['item_qty'];
                    $f_product_price[$j]       = $value['product_price'];
                    $f_product_basic_price[$j] = $value['product_basic_price'];

                    // tagging
                    $f_is_parent_2[$j]      = $value['is_parent_2'];  // tag parent poscode);
                    $f_ref_id[$j]           = $value['ref_id']; // product reference id tag
                    $f_parent_child_tag[$j] = $value['parent_child_tag']; // parent and child poscode

                    $gross_total += $value['product_price'] * $value['item_qty'];
                    $net_total += $value['product_basic_price'] * $value['item_qty'];
                    $quantity_total += $value['item_qty'];

                    $j += 1;
                }
            }

            $item_count = count($f_item_poscode); // count total items



            $items = array(
                $f_item_poscode,
                $f_item_qty,
                $f_product_price,
                $f_product_basic_price,
                $f_is_parent_2,
                $gross_total,
                $net_total,
                $quantity_total,
                $item_count
            );

            return $items;
        } catch (Exception $e) {
            $error_response['status']  = 704;
            $error_response['error']   = "Item Processing Encountered an Error.";
            echo json_encode($error_response);
            die();
        }
    }

    function filter_by_value($array, $index, $value)
    {
        // filter the array with the specified  index and value

        // $OFS_READ = $this->load->database('ofs_read', TRUE);
        // $OFS_WRITE = $this->load->database('ofs_write', TRUE);
        //$newarray = array();

        if (is_array($array) && count($array) > 0) {
            foreach (array_keys($array) as $key) {
                $temp[$key] = $array[$key][$index];

                if ($temp[$key] == $value) {
                    $newarray[$key] = $array[$key];
                }
            }
        }

        //print_r($newarray);
        return $newarray;
    }

    private function get_store_product_list($parameters)
    {

        $sql = $parameters['sql'];

        $q = DB::select($sql);
        $temp = [];
        // process gross price and basic price here...
        $result = $q;

        foreach ($result as &$row) {

            $date_now       = strtotime(date('Y-m-d H:i:s')); // date time now
            $promo_dt_start = strtotime($row->promo_date_start); // promo start
            $promo_dt_end   = strtotime($row->promo_date_end); // promo end

            // get price
            $promo_gross_price = $row->promo_gross_price;
            $promo_basic_price = $row->promo_basic_price;
            $gross_price       = $row->gross_price;
            $basic_price       = $row->basic_price;
            $basic_tax         = $row->basic_tax;
            $promo_tax         = $row->promo_tax;
            // check promotional pricing date
            if (($date_now - $promo_dt_start) >= 0 && ($date_now - $promo_dt_end) <= 0) {
                // promo pricing
                $row->gross_price = $promo_gross_price;
                $row->basic_price = $promo_basic_price;
                $row->tax         = $promo_tax;
            } else {
                // default pricing
                $row->gross_price = $gross_price;
                $row->basic_price = $basic_price;
                $row->tax         = $basic_tax;
            }

            $temp[] = $row;
        }

        return $temp;
    }

    private function cdb_process_customer($details, $is_pickup = false)
    {
        // this will support multiple first names
        $name = trim($details['first_name']) . " " . trim($details['last_name']);
        $name_arr = explode(" ", $name);
        $fullname = "+" . implode(" +", $name_arr);

        $fullname = "+" . trim($details['first_name']) . " +" . trim($details['last_name']);


        $customer_data = Customer::where("home_phone_1", $details['contact'])->first();

        // CHECK IF CUSTOMER IS EXISTING.
        if ($customer_data) {
            $customer_id = $customer_data->id;

            $customer_data->email_address = $details['email_address'];


            // get customer address details
            $address_details = [
                'customer_id'      => $customer_id,
                'customer_address' => $details['address'],
                'landmarks'        => $details['landmarks'],
                'remarks'          => $details['address_remarks'],
                'city'             => $details['city_id'],
                'region'           => $details['region_id']
            ];

            // $this->debugging($address_details, "address information");

            // check cdb address to ofs address reference table
            $cdb_result = "";
            if (!$is_pickup) {
                // $cdb_result = $this->api_model->check_ofs_cdb_address($details['cdb_address_id'], $customer_id); // disabled: temporary
                $cdb_result = CdbAddress::where("cdb_address_id", $details['cdb_address_id'])->where("ofs_user_id", $customer_id)->first();
            }

            if (!$cdb_result) {

                // cdb_address_id not found on ofs address id reference
                // will create the address then add it to the reference table

                // create customer delivery address
                $address = $this->add_address($address_details);
                $ofs_address_id =  $address->id;

                if (!$is_pickup) {
                    $result_cdb_reference = new CdbAddress;
                    $result_cdb_reference->cdb_address_id = $details['cdb_address_id'];
                    $result_cdb_reference->ofs_address_id = $ofs_address_id;
                    $result_cdb_reference->ofs_user_id = $customer_id;
                    $result_cdb_reference->save();
                }
            } else {
                // if cdb address id is found return its ofs address id counter part
                // we will not create a new address for this, we will use ofs address id instead
                $ofs_address_id = $cdb_result->ofs_address_id;

                // then we update the address
                $cdb_address = Address::find($ofs_address_id);
                $cdb_address->address = $details['address'];
                $cdb_address->save();
            }
        } else {


            $new_customer = new Customer;
            $new_customer->first_name      = $details['first_name'];
            $new_customer->last_name       = $details['last_name'];
            $new_customer->home_phone_1    = $details['contact'];
            $new_customer->email_address   = $details['email_address'];
            $new_customer->save();

            $customer_id = $new_customer->id;

            $address_details = [
                'customer_id'      => $customer_id,
                'customer_address' => $details['address'],
                'landmarks'        => $details['landmarks'],
                'remarks'          => $details['address_remarks'],
                'city'             => $details['city_id'],
                'region'           => $details['region_id']
            ];

            $address = $this->add_address($address_details);
            $ofs_address_id =  $address->id;

            if (!$is_pickup) {
                $result_cdb_reference = new CdbAddress;
                $result_cdb_reference->cdb_address_id = $details['cdb_address_id'];
                $result_cdb_reference->ofs_address_id = $ofs_address_id;
                $result_cdb_reference->ofs_user_id = $customer_id;
                $result_cdb_reference->save();
            }
        }

        return ['customer_id' => $customer_id, 'address_id' => $ofs_address_id];
    }

    function add_address($parameters)
    {
        // save customer address

        // $current_user_id  = $this->api_user_id;
        $created_datetime = date('Y-m-d H:i:s');

        $address = new Address;

        $address->customer_id = $parameters['customer_id'];
        $address->address = $parameters['customer_address'];
        $address->city_municipality = $parameters['city'];
        $address->region = $parameters['region'];
        $address->remarks = $parameters['remarks'];

        // $address->current_user_id = $parameters['customer_id'];
        $address->created_datetime = $created_datetime;

        $address->save();

        AddressLandmark::where("address_id", $address->id)->delete();

        $address_landmark = new AddressLandmark;
        $address_landmark->address_id = $address->id;
        $address_landmark->landmarks = $parameters['landmarks'];

        return $address;
    }

    function get_payment_method($payment_method)
    {
        $payment_type = PaymentType::where("api_alias", $payment_method)->first();

        if ($payment_type) {
            return $payment_type->id;
        } else {
            Log::error("get_payment_method: Unknown payment method.");
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array("status" => 91, "error" => "Unknown payment method."));
            die();
        }
    }

    function get_order_source($source)
    {

        $source_type = SourceType::where("api_alias", $source)->first();

        if ($source_type) {
            return $source_type->id;
        } else {
            Log::error("get_payment_method: Unknown order source.");
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array("status" => 91, "error" => "Unknown order source."));
            die();
        }

    }

    private function is_pickup($data)
    {

        if (is_null($data->customer_info["for_pickup"]) || empty($data->customer_info["for_pickup"])) {
            return false;
        }
        if (is_bool($data->customer_info["for_pickup"]) === false) {
            return false;
        }

        // apply pickup rules, store details is required for pickup
        if (is_null($data->store_details) || empty($data->store_details)) {
            Log::error('is_pickup: store_details is missing.');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array("status" => 112, "error" => "store_details is missing."));
            die();
        }

        // Set pickup store name
        $this->store_name = $data->store_details["store_name"];

        return true;
    }

    public function order_monitoring (Request $request, $store_id) 
    {
        $orders = $this->order
                       ->where('store_id', $store_id)
                       ->whereNotIn("status", [5, 6])
                       ->orderBy('is_advance_order', 'DESC')
                       ->orderBy('id', 'desc')
                       ->get();

        // delivery notification trigger
        foreach($orders as $order) {
            if ($order->delivery_bookings) {
                if ($order->delivery_bookings->service_provider == "grabex") {

                    $message = "<b>Grab Express - </b>Order No. " . $order->order_pin . "<br> is now " . $order->delivery_bookings->status . " (". $order->delivery_bookings->delivery_id .")";

                    $notification = Notification::where("message", $message)->first();

                    if (!$notification && $order->delivery_bookings->status != "QUEUEING") {
                        $notification = new Notification;
                        $notification->user_id = Auth::user()->id;
                        $notification->message = $message;
                        $notification->order_id = $order->id;
                        $notification->order_pin = $order->order_pin;
                        $notification->script = "viewOrder(". $order->id .")";
                        $notification->is_completed = 0;
                        $notification->delivery_status = $order->delivery_bookings->status;
                        $notification->is_read = 0;
                        $notification->save();
                    }
                }
            }
        }

        // track the completed grab booking
        $notifications = Notification::where("is_completed", 0)
                                      ->where("delivery_status", "IN_DELIVERY")
                                      ->get();

        foreach($notifications as $notification) {
            $delivery_booking = DeliveryBooking::where("order_id", $notification->order_id)
                                                ->where("status", "COMPLETED")
                                                ->first();
            if ($delivery_booking) {
                $notification_in_delivery = Notification::find($notification->id);
                $notification_in_delivery->is_completed = 1;
                $notification_in_delivery->save();

                $message = "<b>Grab Express - </b>Order No. " . $notification->order_pin . "<br> is now " . $delivery_booking->status . " (". $delivery_booking->delivery_id .")";

                $notification_new = new Notification;
                $notification_new->user_id = Auth::user()->id;
                $notification_new->message = $message;
                $notification_new->order_id = $notification->order_id;
                $notification_new->script = "viewOrder(". $notification->order_id .")";
                $notification_new->is_completed = 0;
                $notification_new->delivery_status = $delivery_booking->status;
                $notification_new->is_read = 0;
                $notification_new->save();
            }
        }



        return OrderMonitoring::collection($orders);
    }

    public function order_details (Request $request, $store_id, $order_id) {
        return $this->getOrder($store_id, $order_id);  
    }

    public function getOrder($store_id, $order_id) {
        $order = $this->order->find($order_id);

        if ($order->status == 1 || $order->status == 7  && $order->is_view == 0) {
                        $order->is_view = 1;
                        $order->view_datetime = date('Y-m-d H:i:s');
                        $order->save();
            
        } else if($order->is_edited >= 1 && $order->is_view == 0) {
                        $order->is_view = 1;
                        $order->view_datetime = date('Y-m-d H:i:s');
                        $order->save();     
            
        }

        $proximity = new ProximityResource($order);
        $order['proximity'] = $proximity;

        return new OrderDetails($order);
    }

    public function acknowledge(Request $request, $store_id, $order_id)
    {
        $order = $this->order->find($order_id);
        $order->status = 2;
        $order->status_text = "Acknowledge";
        $order->acknowledged_by = $request->acknowledged_by;
        $order->acknowledged_datetime = date('Y-m-d H:i:s');
        $order->save();

        $result = $this->getOrder($store_id, $order->id);
        $date = $result->order_date;
        $order_date  = date('Y-m-d h:iA', strtotime($date));
        $order_date_arr = explode(' ', $order_date);
        $delivery_time = date('Y-m-d h:iA',strtotime(date($result->order_date) . ' + ' . $result->promised_time . ' minutes'));
        $delivery_time_arr = explode(' ', $delivery_time);
        $delivery_charges = $result->delivery_charge;
       
        if ($result->source_id == 1 || $result->source_id == 3 || $result->source_id == 6 ) {
            $this->sendEmailOrderStatus(
                "McDelivery Order Confirmation",
                $result->customer->email_address,
                $result->order_pin,
                $result->order_items,
                $result->status,
                $order_date_arr,
                $delivery_time_arr,
                $delivery_charges


            );
        }
        return  $result;
        
    }

    public function rider_assign(Request $request, $store_id, $order_id)
    {
        $order = $this->order->find($order_id);
        $order->status = 8;
        $order->status_text = "Rider Assigned";
        $order->rider_name = $request->rider_name;
        $order->rider_assigned_datetime = date('Y-m-d H:i:s');
        $order->save();

        $result = $this->getOrder($store_id, $order->id);

        if ($result->source_id == 2 || $result->source_id == 3 || $result->source_id == 6 ) {
            $this->sendEmailOrderStatus(
                "Your McDelivery Order is Being Prepared",
                $result->customer->email_address,
                $result->order_pin,
                '',
                $result->status,
                '',
                '',
                ''
            );
        }

        return $result;
    }

    public function rider_out(Request $request, $store_id, $order_id)
    {
        $order = $this->order->find($order_id);
        $order->status = 3;
        $order->status_text = "Rider Out";
        $order->rider_out_datetime = date('Y-m-d H:i:s');
        $order->save();

        $result = $this->getOrder($store_id, $order->id);

        if ($result->source_id == 2 || $result->source_id == 3 || $result->source_id == 6 ) {
            $this->sendEmailOrderStatus(
                "Your McDelivery Order is On Its Way",
                $result->customer->email_address,
                $result->order_pin,
                '',
                $result->status,
                '',
                '',
                ''
            );
        }

        return $result;
    }

    public function rider_back(Request $request, $store_id, $order_id)
    {
        $order = $this->order->find($order_id);
        $order->status = 4;
        $order->status_text = "Rider Back";
        $order->rider_back_datetime = date('Y-m-d H:i:s');
        $order->save();

        $result = $this->getOrder($store_id, $order->id);

        return $result;
    }

    public function order_close(Request $request, $store_id, $order_id)
    {
        $order = $this->order->find($order_id);
        $order->status = 5;
        $order->status_text = "Closed";
        $order->closed_datetime = date('Y-m-d H:i:s');
        $order->customer_receive_datetime = $request->received_datetime;
        $order->closed_by = $request->user_id;
        $order->closed_by_name = $request->username;

        $order->save();

        $result = $this->getOrder($store_id, $order->id);

        return $result;
    }

    public function update_order_receive_datetime(Request $request, $store_id, $order_id)
    {
        $order = $this->order->find($order_id);

        if ($order->received_datetime == null) {
            $order->received_datetime = date('Y-m-d H:i:s');

            $order->save();

            // new order notification
            $notification = new Notification;
            
            $notification->user_id = Auth::user()->id;
            $notification->order_id = $order->id;
            $notification->message = "<b>You</b> have a new order (Order No. " . $order->order_pin . ")";
            $notification->script = "viewOrder(". $order->id .")";
            $notification->is_read = 0;

            $notification->save();
            // $result = $this->getOrder($store_id, $order->id);

          return 1;
        } else {
            return 0;
        }
    }  

    public function advanceOrderNotification(Request $request, $storeId, Order $order)
    {
        $order = Order::where('id', $order->id)->update(['is_view' => 0, 'view_advance' => 1]);
       
    }  
    
    public function order_threshold(Request $request)
    {

      // handles order status time-out threshold.
      $data        = array();
      $dt = "";
      $next_status = $request->next_status;
      $ack_dt      = $request->acknowledged_datetime;
      $ro_dt       = $request->rider_out_datetime;
      $ett         = $request->proximity;

      switch ($next_status) {
          case '3' :
              // RO
              $dt = strtotime('+3 minutes', strtotime($ack_dt));
              break;
          case '4' :
              // RB
              $dt = strtotime('+5 minutes', strtotime($ro_dt));
              break;
          case '5' :
              // CLOSE (ETT based)
              if ($ett <= 0) {
                  $ett = 1;
              } // default to 1 min if <= 0
              $dt = strtotime('+' . $ett . ' minutes', strtotime($ro_dt));
              $dt = date("Y-m-d H:i:s", $dt);
              $dt = strtotime('+3 minutes', strtotime($dt)); // we add addtional 3mins c/o BET
              break;
          default:

      }

      $current_serv_time = date('Y-m-d H:i:s');
      $serv_time         = strtotime($current_serv_time); // current time
      $interval          = ($serv_time - $dt);
      $minutes           = round($interval / 60);

      if ($minutes < 0) {
          $data['allowed'] = false;
      } else {
          $data['allowed'] = true;
      }

      $data['dt'] = date("Y-m-d H:i:s", $dt);
      return $data;
    }

    public function sendEmailOrderStatus($title, $email_address, $order_number, $order_items, $status, $date_order, $delivery_time_arr, $delivery_charges)
    {
        //Composing email
        // Mail::to($email_address)->send(new SendEmail($title, $order_number, $order_items, $status, $date_order, $delivery_time_arr, $delivery_charges));

       
    }

    public function get_cancel_reasons(Request $request, $parent_id = 0)
    {
        $cancel_reasons = CancelReason::whereParentId('0')->get();
        return $cancel_reasons;
    }

    public function update_cancel_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'reason_id' => 'required',
            'canceled_by_name' => 'required',
        ]);

        if ($validator->fails()) {
            foreach ($validator->messages()->getMessages() as $field_name => $messages) {
                // Go through each message for this field.
                foreach ($messages as $message) {
                    return [
                        "error" => true,
                        "message" => $message,
                        "data" => []
                    ];
                }
            }
        } else {
            $order = $this->order::find($request->order_id);
            $order->status = '6';
            $order->canceled_datetime = date('Y-m-d H:i:s');
            // $order->canceled_by = Auth::user()->id;
            $order->status_text = 'Cancelled';
            $order->canceled_by_name = $request->canceled_by_name;
            $order->save();

            $db = new CancelOrderLog;
            $db->order_id = $request->order_id;
            $db->reason_primary_id = $request->reason_id;
            $db->reason_secondary_id = 0;
            $db->notes = $request->cancel_note;
            $db->created_at = date('Y-m-d H:i:s');
            $db->save();

            return [
                "order_id" => $request->order_id,
                "message" => "order has been cancelled",
            ];
        }

    }
    
}

?>