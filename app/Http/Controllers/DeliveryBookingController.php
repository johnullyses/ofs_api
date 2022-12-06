<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Carrier;
use App\Http\Resources\BookingResource\Carrier as CarrierResource;
use App\Http\Resources\ProximityResource\Proximity as ProximityResource;
use App\Http\Resources\OrderResource\OrderDetails as OrderDetails;
use App\Models\Order;
use App\Models\ApiAccess;
use App\Models\DeliveryBooking;
use App\Http\Libraries\GrabExpress;
use App\Http\Libraries\Lalamove;
use App\Http\Libraries\Loginext;
use App\Http\Libraries\Rebrandly;
use config;
use Log;

class DeliveryBookingController extends Controller
{
    private $carrier;
    private $order;
    private $grab;
    private $lalamove;

    public function __construct(Carrier $carrier, Order $order, GrabExpress $grab, DeliveryBooking $orderbooking, Lalamove $lalamove)
    {
        $this->carrier = $carrier;
        $this->order = $order;
        $this->grab = $grab;
        $this->lalamove = $lalamove;
    }

    public function get_carriers() {
        $carrier = $this->carrier
                       ->get();

        return CarrierResource::collection($carrier);
    }

    public function delivery (Request $request, $carrier, $action) {

        $order = $this->order->find($request->order_id);

        $proximity = new ProximityResource($order);
        $order['proximity'] = $proximity;

        $order = json_decode(json_encode(new OrderDetails($order)));


        switch ($carrier) {
            case 'mcd':
                break;
            case 'mds':
                break;
            case 'grabex':

                switch ($action) {
                    case 'book':
                        return $this->book_grab_delivery($order);
                        break;
                    case 'cancel':
                        return $this->cancel_booking_delivery($order->id);
                        break;
                    default:
                        break;
                }

                break;
            case 'lalamove':
                switch ($action) {
                    case 'book':
                        return $this->book_lalamove_delivery($order);
                        break;
                    case 'cancel':
                        return $this->cancel_booking_delivery($order->id);
                        break;
                    default:
                        break;
                }
                break;
            case 'loginext':

                switch ($action) {
                    case 'book':
                        return $this->book_loginext_delivery($order);
                        break;
                    case 'cancel':
                        return $this->cancel_booking_delivery($order);
                        break;
                    default:
                        break;
                }

                break;
            case 'teleport':
                break;
            default:
                break;
        }

        return $order->id;
    }
    function book_lalamove_delivery($order)
    {
        $order_items = [];
        $customer_info = $order->customer;
        $delivery_address = $order->delivery_address;
        $store = $order->store;

        if ($order->payment_id == 1) {
            $order_payment = (float)$order->total_gross;
        }

        foreach ($order->order_items as $order_item) {
            $price_scd = 0;
            $price_non_scd = 0;
            if ($order_item->scd_count) {
                $price_scd = (((floatval($order_item->gross_price / 1.12)) * intval($order_item->scd_count)) * .80);
            }
            $price_non_scd = floatval($order_item->gross_price) * (intval($order_item->quantity) - intval($order_item->scd_count));
            $price = $price_non_scd + $price_scd;
            $order_items[] = [
                'name' => $order_item->product->name,
                'description' => $order_item->product->description,
                'quantity' => intval($order_item->quantity),
                'price' => round($price, 2)
            ];
        }

        $storeLat = $store->y;
        $storeLong = $store->x;
        $deliveryLat = $delivery_address->point_y;
        $delivertLong = $delivery_address->point_x;
        $deliveryContact = $delivery_address->contact_number;
        $data = '{
            "serviceType": "MOTORCYCLE",
            "specialRequests": [],
            "stops": [
              {
                "location": {
                  "lat": "' . $storeLat . '",
                  "lng": "' . $storeLong . '"
                },
                "addresses": {
                  "en_PH": {
                    "displayString": "' . $store->address . '",
                    "market": "PH_MNL"
                  }
                }
              },
              {
                "location": {
                  "lat": "' . $deliveryLat . '",
                  "lng": "' . $delivertLong . '"
                },
                "addresses": {
                  "en_PH": {
                    "displayString": "' . $delivery_address->address . '",
                    "market": "PH_MNL"
                  }
                }
              }
            ],
            "requesterContact": {
              "name": "OFS",
              "phone": "72190922"
            },
            "deliveries": [
              {
                "toStop": 1,
                "toContact": {
                  "name": "' . $customer_info->first_name . '",
                  "phone": "' . $deliveryContact . '"
                },
                "remarks": "Remarks for drop-off point (#1)."
              }
            ],
            "quotedTotalFee": {
                "amount": "500",
                "currency": "PHP"
            }
          }';
        $response = $this->lalamove->book_delivery($data);

        $status = $response["status"];
        $data = $response["data"];
        if ($status == '200') {
            if (!empty($data)) {
                if ($order->id && $response["data"]->orderRef) {
                    $this->update_delivery_status(
                        $order->id,
                        "lalamove",
                        $data,
                        true
                    );
                }
            }
        }

        return $data;
    }

    function book_grab_delivery($order) {
      
        $order_items = [];
        $customer_info = $order->customer;
        $delivery_address = $order->delivery_address;
        $store = $order->store;
        $order_payment = 0;

        if ($order->payment_id == 1){
            $order_payment = (float)$order->total_gross;
        }
    
        foreach($order->order_items as $order_item) {
            $price_scd = 0;
            $price_non_scd = 0;
            if ($order_item->scd_count) {
                    $price_scd = (((floatval($order_item->gross_price/1.12)) * intval($order_item->scd_count)) * .80);
            }
            $price_non_scd = floatval($order_item->gross_price) * (intval($order_item->quantity) - intval($order_item->scd_count));
            $price = $price_non_scd + $price_scd;
            $order_items[] = [
                'name' => $order_item->product->name,
                'description' => $order_item->product->description,
                'quantity' => intval($order_item->quantity),
                'price' => round($price, 2)
            ];
        }
        
        $data = [
            'merchantOrderID' => "$order->id",
            'serviceType' => "INSTANT",
            'sender' => [
                'firstName' => 'OFS',
                'lastName' => $store->store_name,
                'phone' => "72190922",
                'smsEnabled' => true
            ],
            'recipient' => [
                'firstName' => $customer_info->first_name,
                'lastName' => $customer_info->last_name ? $customer_info->last_name : $customer_info->first_name,
                'phone' => $delivery_address->contact_number,
                'smsEnabled' => true,
            ],
            'origin' => [
                'address' => $store->address,
                'coordinates' => [
                    'latitude' => (float)$store->y,
                    'longitude' => (float)$store->x
                ]
            ],
            'destination' => [
                'address' => $delivery_address->address,
                'coordinates' => [
                    'latitude' => (float)$delivery_address->point_y,
                    'longitude' => (float)$delivery_address->point_x
                ]
            ],
            'packages' =>  $order_items,
            'cashOnDelivery' => [
                'amount' => $order_payment
            ],
        ];
        
        $response = $this->grab->book_delivery($order->id, $data);

        $status = $response["status"];
        $data = $response["data"];

        if ($status == '200') {
            if (is_array($data)) {
                if ($order->id && $data['deliveryID']) {
                    $this->update_delivery_status(
                        $order->id,
                        "grabex",
                        $data,
                        true
                    );
                }
            }
        }

        return $data;
    }

    public function get_delivery_booking($order_id)
    {
      
       $orderbooking = DeliveryBooking::where('order_id', $order_id)
                      ->where('datetime_deleted', null)
                      ->take(1)
                      ->orderBy('id', 'DESC')
                      ->first();

        return $orderbooking;
   
    }

    function update_delivery_status($order_id, $source, $booking_details, $isNew = false)
    {
        if ($isNew) {
            if ($source == "lalamove") {
                $result = $this->add_delivery_booking($order_id, $source, $booking_details->status, $booking_details->orderRef);
            } elseif (($order_id == $booking_details['merchantOrderID'])) {
                $result = $this->add_delivery_booking($order_id, $source, $booking_details['status'], $booking_details['deliveryID']);
            } elseif ($source == "loginext") {
                $result = $this->add_loginext_delivery($order_id, $booking_details);
            } elseif ($source == "teleport") {
                $result = $this->add_teleport_delivery($order_id, $booking_details);
            } else {
                return 0;
            }
        } else {
            if ($source == "loginext") {
                $result = $this->update_loginext_delivery($order_id, $booking_details);
            } elseif ($source == "teleport") {
                $result = $this->update_teleport_delivery($booking_details);
            } elseif ($source == 'grabex') {
                $result = $this->update_delivery_booking($order_id, $source, $booking_details);
            } elseif ($source == 'lalamove') {
                $result = $this->update_delivery_booking_lalamove($order_id, $source, $booking_details);
            } else {
                $result = $this->update_delivery_booking($order_id, $source, $booking_details);
            }
        }

        return $result;
    }

    function add_delivery_booking($order_id, $service_provider, $status, $delivery_id)
    {
       $orderbooking = new DeliveryBooking;

       $curr_datetime = date('Y-m-d H:i:s');

       $orderbooking->order_id = $order_id;
       $orderbooking->service_provider = $service_provider;
       $orderbooking->status = $status;
       $orderbooking->delivery_id = $delivery_id;
       $orderbooking->timeline = $status . ':' . $curr_datetime;
        
       if ($orderbooking->save())
       {
        return $orderbooking;
        } 
        else {
        return 0;
       }

    }
    function update_delivery_booking_lalamove($order_id, $source, $booking_details)
    {
        $orderbooking = DeliveryBooking::where('delivery_id', $booking_details['id'])->where('datetime_deleted', null)->first();
        $curr_datetime = date('Y-m-d H:i:s');
        $orderbooking->status = $booking_details['status'];
        $orderbooking->track_url = $booking_details['shareLink'];
        $orderbooking->timeline = $orderbooking->timeline . ', ' . $booking_details['status'] . ':' . $curr_datetime;
        $orderbooking->save();

        if (isset($booking_details['failedReason'])) {
            $orderbooking->fail_reason = $booking_details['failedReason'];
        }

        //update ofs order status
        $order_data = Order::where('id', $orderbooking['order_id'])->first();
        if ($orderbooking && $source == "lalamove" &&  $orderbooking->status) {

            switch ($orderbooking->status) {
                    //For First Rider Cancelling
                case "ASSIGNING_DRIVER":
                    // auto rider assign
                    $order_data->status = 8;
                    $order_data->status_text = "ASSIGNING_DRIVER";

                    $app = app();
                    $data = $app->make('stdClass');
                    $data->orderRef = $order_id;
                    $data->driverId = $booking_details['driverId'];


                    $orderbooking->driver_name = "";
                    $orderbooking->driver_phone = "";
                    $orderbooking->driver_license_plate = "";
                    $orderbooking->driver_photo_url = "";
                    $orderbooking->driver_current_lat = "";
                    $orderbooking->driver_current_lon = "";

                    //Overwrite First Rider
                    $order_data->rider_name = "";
                    $order_data->rider_assigned_datetime = null;

                    $orderbooking->status = $booking_details['status'];
                    $orderbooking->save();
                    break;
                case "ON_GOING":
                    // auto rider assign
                    $order_data->status = 8;
                    $order_data->status_text = "ON GOING";
                    $app = app();
                    $data = $app->make('stdClass');
                    $data->orderRef = $order_id;
                    $data->driverId = $booking_details['driverId'];

                    $driverInformation = $this->lalamove->get_driver_information($data);
                    $driverLocation = $this->lalamove->get_driver_location($data);

                    $orderbooking->driver_name = $driverInformation['data']->name;
                    $orderbooking->driver_phone = $driverInformation['data']->phone;
                    $orderbooking->driver_license_plate = $driverInformation['data']->plateNumber;
                    $orderbooking->driver_photo_url = $driverInformation['data']->photo;
                    $orderbooking->driver_current_lat = $driverLocation['data']->location->lat;
                    $orderbooking->driver_current_lon = $driverLocation['data']->location->lng;

                    $order_data->rider_name = "LALAMOVE-" . $driverInformation['data']->name;
                    $order_data->rider_assigned_datetime = null;
                    $curr_datetime = date('Y-m-d H:i:s');
                    $orderbooking->status = $booking_details['status'];
                    $orderbooking->save();

                    break;
                case "PICKED_UP":
                    // auto rider assign
                    $order_data->status = 3;
                    $order_data->status_text = "PICKED UP";
                    $curr_datetime = date('Y-m-d H:i:s');
                    $order_data->rider_assigned_datetime = $curr_datetime;

                    $orderbooking->status = $booking_details['status'];
                    $orderbooking->save();
                    break;
                case "REJECTED":
                    //if ($orderbooking->fail_reason) {
                    // auto cancel booking
                    $this->cancel_delivery_booking($order_data->id, 'Rider Rejected');
                    //}

                    break;

                case "COMPLETED":
                    // auto customer receive
                    $order_data->customer_receive_datetime = $curr_datetime;
                    // auto rider back
                    $order_data->status = 4;
                    $order_data->status_text = "Rider Back";
                    $order_data->rider_back_datetime = $curr_datetime;
                    // auto close
                    // $this->config->load('grab_express_config', TRUE);
                    $order_data->status = 5;
                    $order_data->status_text = "Closed";
                    $order_data->closed_datetime = $curr_datetime;
                    $order_data->closed_by = config('grabexpress.api_user_id');
                    $order_data->closed_by_name = config('grabexpress.api_username');

                    break;

                default:
                    break;
            }

            $order_data->save();
        }

        return $orderbooking;

    }   
    function update_delivery_booking($order_id, $source, $booking_details)
    {
        $orderbooking = DeliveryBooking::where('order_id', $order_id)->where('delivery_id', $request->delivery_id)->where('datetime_deleted', null)->first();
        $curr_datetime = date('Y-m-d H:i:s');
    
        $orderbooking->status = $booking_details['status'];
        $orderbooking->track_url = $booking_details['trackURL'];
        $orderbooking->pickup_pin = $booking_details['pickupPin'];
        $orderbooking->driver_name = $booking_details['driver']['name'];
        $orderbooking->driver_phone = $booking_details['driver']['phone'];
        $orderbooking->driver_license_plate = $booking_details['driver']['licensePlate'];
        $orderbooking->driver_photo_url = $booking_details['driver']['photoURL'];
        $orderbooking->driver_current_lat = $booking_details['driver']['currentLat'];
        $orderbooking->driver_current_lon =$booking_details['driver']['currentLng'];
        $orderbooking->timeline = $orderbooking->timeline . ', ' . $booking_details['status'] . ':' . $curr_datetime;
        $orderbooking->save();

        if ($booking_details['failedReason']) {
            $orderbooking->fail_reason = $booking_details['failedReason'];
        }

        //update ofs order status
        $order_data = Order::where('order_id', $order_id)->first();
        if ($orderbooking && $source == "grabex" &&  $orderbooking->status) {
           
            switch ($orderbooking->status) {
                case "PICKING_UP":
                    // auto rider assign
                    $order_data->status = 8;
                    $order_data->status_text = "Rider Assigned";
                    $order_data->rider_name = "GRAB-" . $orderbooking->driver_name;
                    $order_data->rider_assigned_datetime = $curr_datetime;

                    break;

                case "IN_DELIVERY":
                    // auto rider assign
                    $order_data->status = 3;
                    $order_data->status_text = "Rider Out";
                    $order_data->rider_assigned_datetime = $curr_datetime;

                    break;

                case "FAILED":
                    if ($orderbooking->fail_reason) {
                        // auto cancel booking
                       $this->cancel_delivery_booking($order_id, $booking_details['failedReason']);
                    }

                    break;

                case "RETURNED":
                    // auto rider back
                    /*$order_data['status'] = 4;
                    $order_data['status_text'] = "Rider Back";
                    $order_data['rider_back_datetime'] = $curr_datetime;*/
                    // auto close
                    /*$this->config->load('grab_express_config', TRUE);
                    $order_data['status'] = 6;
                    $order_data['status_text'] = "Cancelled";
                    $order_data['canceled_datetime'] = $curr_datetime;
                    $order_data['canceled_reason'] = "GRAB-Failed Delivery";
                    $order_data['canceled_by'] = $this->config->item('api_user_id', 'grab_express_config');
                    $order_data['canceled_by_name'] = $this->config->item('api_username', 'grab_express_config');*/

                    break;

                case "COMPLETED":
                    // auto customer receive
                    $order_data->customer_receive_datetime = $curr_datetime;
                    // auto rider back
                    $order_data->status = 4;
                    $order_data->status_text = "Rider Back";
                    $order_data->rider_back_datetime = $curr_datetime;
                    // auto close
                    $this->config->load('grab_express_config', TRUE);
                    $order_data->status = 5;
                    $order_data->status_text = "Closed";
                    $order_data->closed_datetime = $curr_datetime;
                    $order_data->closed_by = config('grabexpress.api_user_id');
                    $order_data->closed_by_name = config('grabexpress.api_username');

                    break;

                default:
                    break;
            }

         $order_data->save();
        }

        return $orderbooking;

    }
    
    public function grab_delivery_status_changed(Request $request)
    {
        $data = $request->data['contents'];
        $app_id             = $request->data['api_id'];
        $public_key         = $request->data['api_key'];
        $request_signature  = $request->signature;
        $private_key        = $this->get_private_key($app_id, $public_key);
        $server_hash        = $this->create_hash(json_encode($request->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $private_key);
        $is_request_valid   = $this->validate_signature($request_signature, $server_hash);

        if (!$is_request_valid) {
            Log::error("Invalid Request Signature");
            echo json_encode(array("status" => 401, "error" => "Unauthorized"), JSON_PRETTY_PRINT);
            die();
        }

        return $this->update_delivery_status($request['data']['contents']['data']['merchantOrderID'], "grabex", $request['data']['contents']['data'], false);
    }
    public function lalamove_delivery_status_changed(Request $request)
    {

        $data = $request->data['contents'];

        $app_id             = $request->data['api_id'];
        $public_key         = $request->data['api_key'];
        $request_signature  = $request->signature;
        $private_key        = $this->get_private_key($app_id, $public_key);
        $server_hash        = $this->create_hash(json_encode($request->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $private_key);
        $is_request_valid   = $this->validate_signature($request_signature, $server_hash);

        if (!$is_request_valid) {
            Log::error("Invalid Request Signature");
            echo json_encode(array("status" => 401, "error" => "Unauthorized"), JSON_PRETTY_PRINT);
            die();
        }

        //dd($data['data']['data']['order']);
        return $this->update_delivery_status($data['data']['data']['order']['id'], "lalamove", $data['data']['data']['order'], false);
    }

    private function create_hash($message, $private_key)
    {
        return base64_encode(hash_hmac('sha256', $message, $private_key, TRUE));
    }

    private function validate_signature($request_signature, $server_signature)
    {
        return true;
        // if ($request_signature === $server_signature) {
        //     return true;
        // }else {
        //     return false;
        // }
    }

    private function get_private_key($app_id, $app_public_token)
    {
        $result = ApiAccess::where('app_id', $app_id)->where('app_public_token', $app_public_token)->first();

        if (!$result) {
            echo json_encode(array("success" => false, "errormsg" => "Application Not Recognized."));
            die();
        }

        $app_private_token = $result->app_private_token;

        return $app_private_token;
    }

    public function cancel_booking_delivery($order_id)
    {
        $delivery_booking = $this->get_delivery_booking($order_id);

        $response = [];

        if ($delivery_booking) {
            if ($delivery_booking->service_provider == "loginext") {
                $response = Loginext::cancel_book_delivery($order_id, $delivery_booking->pickup_pin);
            } 
            if ($delivery_booking->service_provider == "teleport") {
                $response = Teleport::cancel_book_delivery($order_id, $delivery_booking->delivery_id);
            } 
            if ($delivery_booking->service_provider == "grabex") {
                $response = $this->grab->cancel_book_delivery($order_id, $delivery_booking->delivery_id);
            }
        }

        $status = $response["status"];
        $data = $response["data"];

        if ($order_id && $status == '204') {
            $this->cancel_delivery_booking($order_id);
        }

        return $response["data"];
    }

    public function cancel_delivery_booking($order_id,  $cancel_reason= null)
    {
        $orderbooking = DeliveryBooking::where('order_id', $order_id)->where('datetime_deleted', null)->first();
        $curr_datetime = date('Y-m-d H:i:s');

        $short_url = $this->get_short_url($orderbooking->delivery_id);
        if ($short_url->short_url) {
            $this->rebrandly->delete_short_link($short_url->short_url_id);
        }
       
        $orderbooking->status = "CANCELLED";
        $orderbooking->cancel_reason = $cancel_reason;
        $orderbooking->datetime_deleted = $curr_datetime;
        $orderbooking->timeline = $orderbooking->timeline . ', CANCELLED:' . $curr_datetime;

        $order_data = Order::where('id', $order_id)->first();

        if ($orderbooking && $order_data->status == 8) {
        $order_data->status = 2;
        $order_data->status_text = "Acknowledge";
        $order_data->rider_assigned_datetime = "";
        $order_data->rider_name = "";
        $order_data->save();
        }
       
        if($orderbooking->save())
        {
            return $orderbooking;
        } 
        else {
            return 0;
        }
    }
    

    function book_loginext_delivery($order) {

        $order_date = strtotime($order->order_date);
        $date_now = strtotime(date("Y-m-d H:i:s"));
        $store_code = str_pad($order->store->code, 4, '0', STR_PAD_LEFT);
        $payment_type = "COD";
        if ($order->payment_id == 3 || $order->payment_id == 8) {
            $payment_type = "Prepaid";
        }

        $enable_fleet_sharing = config('loginext.enable_fleet_sharing');

        if($enable_fleet_sharing){
            $order->store->loginext_auto_assign_profile = $order->store->loginext_auto_assign_profile != "" ? $order->store->loginext_auto_assign_profile : "DEFAULT";
        }else{
            $order->store->loginext_auto_assign_profile = "DEFAULT";
        }

        $data = array(
            array(
                'orderNo' => $order->order_pin,
                'shipmentOrderTypeCd' => "DELIVER",
                'orderState' => "FORWARD",
                'shipmentOrderDt' => gmdate("Y-m-d\TH:i:s.000\Z", $order_date),
                'autoAllocateFl' => "Y",
                'autoAllocateProfileName' => $order->store->loginext_auto_assign_profile != "" ? $order->store->loginext_auto_assign_profile : "DEFAULT",
                'distributionCenter' => $store_code,
                'paymentType' => $payment_type,
                'packageValue' => $order->total_gross,
                'deliverBranch' => $store_code,
                'deliverEndTimeWindow' => gmdate("Y-m-d\TH:i:s.000\Z", strtotime('+' . $order->promised_time . ' minutes' , $date_now)),
                'deliverStartTimeWindow' => gmdate('Y-m-d\TH:i:s.000\Z', $date_now),
                'deliverAccountCode' => $order->customer->id,
                'deliverAccountName' => $order->customer->first_name . ' ' . $order->customer->last_name,
                'pickupEmail' => $order->customer->email_address,
                'deliverPhoneNumber' => $order->delivery_address->contact_number,
                'deliverApartment' => "-",
                'deliverStreetName' => $order->delivery_address->address,
                'deliverLocality' => "-",
                'deliverCity' => $order->city_name,
                'deliverCountry' => "PHL",
                'returnBranch' => $store_code,
                'cancellationAllowedFl' => 'Y',
            )
        );


        $send_long_lat = config('loginext.send_long_lat');
        if ($send_long_lat) {
            $data[0]['deliverLatitude'] = (float)$delivery_address->point_y;
            $data[0]['deliverLongitude'] = (float)$delivery_address->point_x;
        }

        if ($order->store->loginext_auto_assign_profile != "" && $order->store->loginext_deliver_type != "" && $enable_fleet_sharing) {
            $data[0]['deliveryType'] = $order->store->loginext_deliver_type;
        }

        return Loginext::book_delivery($order->id, $data);
    }

    function add_loginext_delivery($order_id, $booking_details)
    {
        $orderbooking = new DeliveryBooking;
 
        $orderbooking->order_id = $order_id;
        $orderbooking->service_provider = "loginext";
        $orderbooking->status = "UNASSIGNED";
        $orderbooking->pickup_pin = $booking_details['referenceId'];
        $orderbooking->delivery_id = $booking_details['orderNumber'];
        $orderbooking->timeline = "UNASSIGNED:" . date('Y-m-d H:i:s');
         
        if ($orderbooking->save()) {
            return $orderbooking;
        } else {
            return 0;
        }
    }

    function update_loginext_delivery($order_id, $booking_details)
    {

        if ($booking_details['status']) {
            if ($booking_details['status'] == "ORDER ACCEPTED") {
                $order_no = $booking_details['clientShipmentId'];
                $data = array(
                    'status' => "ORDER_ACCEPTED",
                    'track_url' => $booking_details['trackUrl'],
                    'driver_name' => $booking_details['deliveryMediumName'],
                );
            }
            if ($booking_details['status'] == "PICKEDUP") {
                $order_no = $booking_details['orderNo'];
                $data = array(
                    'status' => "PICKEDUP",
                    'driver_name' => $booking_details['deliveryMediumName'],
                );
            }
        }

        if ($booking_details['notificationType']) {
            if ($booking_details['clientShipmentIds']) {
                $order_no = $booking_details['clientShipmentIds'][0];
                $query_params = array(
                    'service_provider' => "loginext",
                    'delivery_id' => $order_no,
                    'datetime_deleted' => null,
                );
            } else {
                $order_no = $booking_details['orderNo'];
                $query_params = array(
                    'service_provider' => "loginext",
                    'delivery_id' => $order_no,
                    'pickup_pin' => $booking_details['orderReferenceId'],
                    'datetime_deleted' => null,
                );
            }
            if ($booking_details['notificationType'] == "ORDERALLOCATIONSTOP" && $booking_details['isMaxAttemptsExhausted'] == true) {
                $data = array(
                    'status' => "RIDER_ALLOCATION_FAILED"
                );
            }
            if ($booking_details['notificationType'] == "STARTTRIP") {
                $data = array(
                    'status' => "ORDER_ACCEPTED",
                    'driver_name' => $booking_details['deliveryMediumName'],
                );
            }
            if ($booking_details['notificationType'] == "NOTPICKEDUPNOTIFICATION") {
                $data = array(
                    'status' => "PICKUP_FAILED"
                );
            }
            if ($booking_details['notificationType'] == "NOTDELIVEREDNOTIFICATION") {
                $data = array(
                    'status' => "DELIVER_FAILED"
                );
            }
            if ($booking_details['notificationType'] == "DELIVEREDNOTIFICATION") {
                $data = array(
                    'status' => "DELIVERED"
                );
            }
            if ($booking_details['notificationType'] == "CANCELLEDNOTIFICATION") {
                return $this->cancel_delivery_booking($order_id, "LN-" . $booking_details['reason']);
            }
        }
        // Generate Short Tracking URL
        if ($data['status'] == "ORDER_ACCEPTED") {
            $booking_is_deleted = $this->booking_is_deleted($order_no);
            if ($booking_is_deleted) {
                unset($query_params['datetime_deleted']);
                $data['datetime_deleted'] = NULL;
            }
            // Check if URL already exists
            // Skip if it exist
            $short_url = $this->get_short_url($order_no);
            if (!$short_url->short_url_id || $short_url->short_url_id == "" || $short_url->short_url_id == NULL) {
                $rebrandly_return = Rebrandly::generate_short_link($order_no);
                $data['short_url'] = $rebrandly_return['shortUrl'];
                $data['short_url_id'] = $rebrandly_return['id'];
            }
        }

        // Delete Short URL on rebrandly
        if ($data['status'] == "DELIVERED" ||
            $data['status'] == "PICKUP_FAILED" ||
            $data['status'] == "DELIVER_FAILED")
        {
            $short_url = $this->get_short_url($order_no);
            $this->rebrandly->delete_short_link($short_url->short_url_id);
        }

        // if ($booking_details['failedReason']) {
        //     $data['fail_reason'] = $booking_details['failedReason'];
        // }

        $OFS_WRITE = $this->load->database('ofs_write', TRUE);

        $curr_datetime = date('Y-m-d H:i:s');

        $OFS_WRITE->set('timeline', 'CONCAT(timeline, ", ", "'. $data['status'] . ':' . $curr_datetime . '")', FALSE);
        $OFS_WRITE->where($query_params);
        $OFS_WRITE->update('ofs_delivery_bookings', $data);

        $row_affected = $OFS_WRITE->affected_rows();

        //update ofs order status
        if ($row_affected && $data['status']) {
            $order_data = array();
            switch ($data['status']) {
                case "ORDER_ACCEPTED":
                    // auto rider assign
                    $order_data['status'] = 8;
                    $order_data['status_text'] = "Rider Assigned";
                    $order_data['rider_name'] = "Loginext-" . $data['driver_name'];
                    $order_data['rider_assigned_datetime'] = $curr_datetime;

                    break;

                case "PICKEDUP":
                    // auto rider out
                    $order_data['status'] = 3;
                    $order_data['status_text'] = "Rider Out";
                    $order_data['rider_out_datetime'] = $curr_datetime;

                    break;

                case "DELIVERED":
                    // auto customer receive
                    $order_data['customer_receive_datetime'] = $curr_datetime;
                    // auto rider back
                    // $order_data['status'] = 4;
                    // $order_data['status_text'] = "Rider Back";
                    $order_data['rider_back_datetime'] = $curr_datetime;
                    // auto close
                    $order_data['status'] = 5;
                    $order_data['status_text'] = "Closed";
                    $order_data['closed_datetime'] = $curr_datetime;
                    $order_data['closed_by'] = config("loginex.api_user_id");
                    $order_data['closed_by_name'] = config("loginex.api_username");

                    break;

                default:
                    break;
            }
            if ($data['status'] == "PICKUP_FAILED" || $data['status'] == "DELIVER_FAILED" || $data['status'] == "RIDER_ALLOCATION_FAILED") {
                return 1;
            }
            return $this->update_ofs_order_status($order_id, $order_data, 'loginext');
        }

        return 0;

    }

    function get_short_url($delivery_id)
    {
        $orderbooking = DeliveryBooking::where('delivery_id', $delivery_id)
                            ->orderBy('id', 'DESC')
                            ->first();

        return $orderbooking;
    }

    function booking_is_deleted($delivery_id)
    {
        $orderbooking = DeliveryBooking::where('delivery_id', $delivery_id)
                        ->where('datetime_deleted', "!=", null)
                        ->orderBy('datetime_created', 'DESC')
                        ->first();

        if ($orderbooking) {
            return 1;
        } else {
            return 0;
        }
    }

}