<?php 

namespace App\Http\Libraries;

use config;
use Illuminate\Support\Facades\Log;

class GrabExpress {

	public function __construct()
	{
        $this->api_url = config('grabexpress.api_url');
        $this->api_username = config('grabexpress.api_username');
        $this->api_user_id = config('grabexpress.api_user_id');
        $this->auth_api_url = config('grabexpress.auth_api_url');
        $this->client_id = config('grabexpress.client_id');
        $this->secret = config('grabexpress.secret');
    }

    // Request for delivery service
    public function book_delivery($order_id, $data)
    {
        return $this->send_to_api("book_delivery", "POST", "/v1/deliveries", $order_id, $data);
    }

    // Request for delivery service qoutes
    public function get_delivery_quotes($data)
    {
        return $this->send_to_api("get_delivery_quotes", "POST", "/v1/deliveries/quotes", $data);
    }

    // Request for delivery service
    public function get_book_delivery($delivery_id)
    {
        return $this->send_to_api("get_book_delivery", "GET", "/v1/deliveries/$delivery_id");
    }

    // Cancel a delivery
    public function cancel_book_delivery($order_id, $delivery_id)
    {
        return $this->send_to_api("cancel_book_delivery", "DELETE", "/v1/deliveries/$delivery_id", $order_id);
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
        $request_body_json_encode = json_encode(array(
            'client_id' => $this->client_id,
            'client_secret' => $this->secret,
            'scope' => 'grab_express.partner_deliveries',
            'grant_type' => 'client_credentials',
        ));

        $curl = curl_init($this->auth_api_url);
        curl_setopt($curl, CURLOPT_URL, $this->auth_api_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body_json_encode);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($curl, CURLOPT_VERBOSE, 1);
        // curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($curl);
   
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $bearer_token = json_decode($response, true)['access_token'];

        $headers = array(
            'Content-Type' => $request_type == "GET" ? "" : "application/json",
            'Date' => gmdate("D, d M Y H:i:s T")
        );

        $request_body_json_encode = json_encode($request_body);

        $content_digest = base64_encode(hash("SHA256", $request_body_json_encode, TRUE));

        $string_to_sign = $request_type . "\n" .
                        $headers['Content-Type'] . "\n" .
                        $headers['Date'] . "\n" .
                        $action . "\n" .    
                        $content_digest . "\n";

        $hmac_signature = hash_hmac("SHA256", $string_to_sign, $this->secret, TRUE);

        $base64_encoded_hmac_signature = base64_encode($hmac_signature);

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
            'Date: ' . $headers['Date'],
            'Authorization: Bearer ' . $bearer_token
        ));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $decoded_response = json_decode($response, true);

        $this->log($method, $action, is_array($request_body) ? $request_body_json_encode : "NA", $response ? "$status:$response" : $status);
    
        return [
            "status" => $status,
            "data" => $decoded_response
        ];
    }

    private function log($method, $url, $request = null, $response = null)
    {
        $log_data = " $method($url) : Request($request) Response($response) \n";

        Log::channel('grabex')->info($log_data);
    } 

} /* END  OF CLASS */
