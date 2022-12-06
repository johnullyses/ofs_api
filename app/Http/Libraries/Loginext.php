<?php

namespace App\Http\Libraries;

use config;
use Illuminate\Support\Facades\Log;

class Loginext {

	public function __construct()
	{
        // GrabEx API URL
        $this->api_url = config('loginext.api_url');
        // Client Credentials
        $this->token = config('loginext.token');

    }

    // Request for create delivery
    function book_delivery($order_id, $data)
    {
        $this->send_to_api("book_delivery", "POST", "v2/create", $order_id, $data);
    }

    // // Request for delivery service qoutes
    // function get_delivery_quotes($data)
    // {
    //     $this->send_to_api("get_delivery_quotes", "POST", "/v1/deliveries/quotes", $data);
    // }

    // // Request for delivery service
    // function get_book_delivery($delivery_id)
    // {
    //     $this->send_to_api("get_book_delivery", "GET", "/v1/deliveries/$delivery_id");
    // }

    // Cancel a delivery
    function cancel_book_delivery($order_id, $delivery_id)
    {
        $this->send_to_api("cancel_book_delivery", "PUT", "v1/cancel", $order_id, [ $delivery_id ]);
    }

    /**
     * Send API Request to Grab Express
     * 
     * @param String $method
     * @param String $request_type ['POST', 'GET', 'DELETE', etc]
     * @param String $action [ url e.g. '/v1/deliveries/']
     * @param Int $order_id
     * @param Array $request_body
     */
    function send_to_api($method, $request_type, $action, $order_id = null, $request_body = null)
    {
        $headers = array(
            'Content-Type' => $request_type == "GET" ? "" : "application/json",
            'Date' => gmdate("D, d M Y H:i:s T")
        );

        $request_body_json_encode = json_encode($request_body);

        $url = $this->api_url . $action;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request_type);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body_json_encode);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($curl, CURLOPT_VERBOSE, 1);
        // curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: ' . $headers['Content-Type'],
            'WWW-Authenticate: BASIC ' . $this->token
        ));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $decoded_response = json_decode($response, true);

        $this->log($method, $action, count($request_body) ? $request_body_json_encode : "NA", $response ? "$status:$response" : $status);

        return [
            "status" => $status,
            "data" => $decoded_response["data"][0]
        ];
    }

    private function log($method, $url, $request = null, $response = null)
    {
        $curr_date = date('Y-m-d');
        $curr_time = date('H:i:s');

        $file_name = "$curr_date.log";
        $log_data = "$curr_time | $method($url) : Request($request) Response($response) \n";

        Log::info($log_data);
    } 
} /* END  OF CLASS */
