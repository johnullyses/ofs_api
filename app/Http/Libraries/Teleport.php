<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library will manage ChatBot API calls
 */
class Teleport {

	public function __construct()
	{
        $this->ci =& get_instance();
        $this->ci->config->load('teleport_config', TRUE);
        $this->ci->load->model('delivery_booking_model');

        // Teleport API URL
        $this->api_url = $this->ci->config->item('api_url', 'teleport_config');
        // Client Credentials

        $this->token = $this->get_auth_token();

    }
    
    function get_auth_token()
    {
        $token = $this->ci->delivery_booking_model->get_teleport_api_token();

        if ($token) {
            $curr_time = date('Y-m-d H:i:s');
            $time_passed = strtotime($curr_time) - strtotime($token['datetime_created']);
        }

        if ($token == null || $time_passed >= 39600) {
            $new_token = $this->request_token(); // request new token
            $result = $this->ci->delivery_booking_model->save_new_teleport_api_token($new_token); //save new token
            if ($result) {
                return $new_token;
            }
            return 0;
        }
        return $token['token'];
    }

    // Request for token
    function request_token()
    {
        return $this->send_to_api("request_token", "POST", "1/sign-in", "", array(
            'email' => $this->ci->config->item('auth_username', 'teleport_config'),
            'password' => $this->ci->config->item('auth_password', 'teleport_config')
        ));
    }

    // Request for create delivery
    function book_delivery($order_id, $data)
    {
        $this->send_to_api("book_delivery", "POST", "1/order", $order_id, $data);
    }

    // Cancel a delivery
    function cancel_book_delivery($order_id, $delivery_id)
    {
        $this->send_to_api("cancel_book_delivery", "POST", "1/order/cancel", $order_id, array('tracking_number' => $delivery_id));
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
        );

        $request_body_json_encode = json_encode($request_body);

        $url = $this->api_url . $action;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request_type);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body_json_encode);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: ' . $headers['Content-Type'],
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token
        ));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $decoded_response = json_decode($response, true);

        switch ($status) {
            case '200':
                if (is_array($decoded_response)) {
                    if ($method == "request_token") {
                        $this->log($method, $action, count($request_body) ? $request_body_json_encode : "NA", $response ? "$status:$response" : $status);
                        return $decoded_response['token'];
                    }
                }
                break;
            case '201':
                if (is_array($decoded_response)) {
                    // die("test");
                    if ($method == "book_delivery" && $order_id) {
                        $this->ci->delivery_booking_model->update_delivery_status(
                            $order_id,
                            "teleport",
                            $decoded_response,
                            true
                        );
                    }
                }
                break;
            case '204':
                if ($method == "cancel_book_delivery" && $order_id) {
                    $this->ci->delivery_booking_model->cancel_delivery_booking($order_id);
                }
                break;
            default:
                // die("Error: call to URL $url failed with status $status, response $response, curl_error "
                //     . curl_error($curl) . ", curl_errno " . curl_errno($curl)."status ".$status);
                break;
        }

        $this->log($method, $action, count($request_body) ? $request_body_json_encode : "NA", $response ? "$status:$response" : $status);

        // header('Content-type: application/json');
        // echo $response;
    }

    private function log($method, $url, $request = null, $response = null)
    {
        $this->ci->load->helper('file');

        $curr_date = date('Y-m-d');
        $curr_time = date('H:i:s');

        $file_name = "$curr_date.log";
        $log_data = "$curr_time | $method($url) : Request($request) Response($response) \n";

        if ( ! write_file(APPPATH.'logs/teleport/'.$file_name, $log_data, 'a+')) {
            //echo 'Unable to write the file. - '.APPPATH;
        } else {
            //echo 'File written! - '.APPPATH;;
        }
    }
} /* END  OF CLASS */
