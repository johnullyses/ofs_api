<?php

namespace App\Http\Libraries;

use config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Lalamove
{

    public function __construct()
    {
        $this->api_url = config('lalamove.api_url');
        $this->client_id = config('lalamove.client_id');
        $this->api_key = config('lalamove.api_key');
        $this->secret = config('lalamove.secret');
    }
    // Request for delivery service
    public function book_delivery($data)
    {
        $url = $this->api_url . "/v2/orders";

        $response =  $this->send_to_api($url, $data);
        $response = json_decode($response);
        if (isset($response->orderRef)) {
            return $order_status = $this->check_order_status($response);
        } else {
            return false;
        }
        $url = $this->api_url . "/v2/orders";
    }

    function send_to_api($hostname, $data)
    {
        $curl = curl_init();
        $milliseconds = round(microtime(true) * 1000);
        $timestamp = $milliseconds;
        $http_verb = 'POST';
        $path = '/v2/orders';
        $body = $data;
        $apiKey = $this->api_key;
        $secret = $this->secret;

        $data = $timestamp . "\r\n" . $http_verb . "\r\n" . $path . "\r\n\r\n" . $body;
        $signature = hash_hmac(
            'sha256',
            $data,
            $secret
        );

        $authorization = "hmac " . $apiKey . ":" . $milliseconds . ":" . $signature;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $hostname,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $http_verb,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: $authorization",
                "X-LLM-market: PH_MNL"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }
    function check_order_status($data)
    {
        $curl = curl_init();
        $orderref = $data->orderRef;
        $milliseconds = round(microtime(true) * 1000);
        $timestamp = $milliseconds;
        $http_verb = 'GET';
        $path = '/v2/orders/' . $data->orderRef;
        $hostname = $this->api_url . $path;
        $apiKey = $this->api_key;
        $secret = $this->secret;

        $data = $timestamp . "\r\n" . $http_verb . "\r\n" . $path . "\r\n\r\n";
        $signature = hash_hmac(
            'sha256',
            $data,
            $secret
        );

        $authorization = "hmac " . $apiKey . ":" . $milliseconds . ":" . $signature;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $hostname,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $http_verb,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: $authorization",
                "X-LLM-market: PH_MNL"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $decoded_response = json_decode($response);
        if ($status == 200) {
            $decoded_response->orderRef = $orderref;
        }
        return [
            "status" => $status,
            "data" => $decoded_response
        ];
    }
    function cancel_order($data)
    {
        $curl = curl_init();
        $orderref = $data->orderRef;
        $milliseconds = round(microtime(true) * 1000);
        $timestamp = $milliseconds;
        $http_verb = 'GET';
        $path = '/v2/orders/' . $data->orderRef . '/cancel';
        $hostname = $this->api_url . $path;
        $apiKey = $this->api_key;
        $secret = $this->secret;

        $data = $timestamp . "\r\n" . $http_verb . "\r\n" . $path . "\r\n\r\n";
        $signature = hash_hmac(
            'sha256',
            $data,
            $secret
        );

        $authorization = "hmac " . $apiKey . ":" . $milliseconds . ":" . $signature;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $hostname,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $http_verb,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: $authorization",
                "X-LLM-market: PH_MNL"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $decoded_response = json_decode($response);
        if ($status == 200) {
            $decoded_response->orderRef = $orderref;
        }
        return [
            "status" => $status,
            "data" => $decoded_response
        ];
    }
    function get_driver_information($data)
    {
        $curl = curl_init();
        $orderref = $data->orderRef;
        $milliseconds = round(microtime(true) * 1000);
        $timestamp = $milliseconds;
        $http_verb = 'GET';
        $path = '/v2/orders/' . $data->orderRef;
        $path = $path . '/drivers' . '/' . $data->driverId;
        $hostname = $this->api_url . $path;
        $apiKey = $this->api_key;
        $secret = $this->secret;

        $data = $timestamp . "\r\n" . $http_verb . "\r\n" . $path . "\r\n\r\n";
        $signature = hash_hmac(
            'sha256',
            $data,
            $secret
        );

        $authorization = "hmac " . $apiKey . ":" . $milliseconds . ":" . $signature;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $hostname,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $http_verb,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: $authorization",
                "X-LLM-market: PH_MNL"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $decoded_response = json_decode($response);
        if ($status == 200) {
            $decoded_response->orderRef = $orderref;
        }
        return [
            "status" => $status,
            "data" => $decoded_response
        ];
    }
    function get_driver_location($data)
    {
        $curl = curl_init();
        $orderref = $data->orderRef;
        $milliseconds = round(microtime(true) * 1000);
        $timestamp = $milliseconds;
        $http_verb = 'GET';
        $path = '/v2/orders/' . $data->orderRef;
        $path = $path . '/drivers' . '/' . $data->driverId . '/location';
        $hostname = $this->api_url . $path;
        $apiKey = $this->api_key;
        $secret = $this->secret;

        $data = $timestamp . "\r\n" . $http_verb . "\r\n" . $path . "\r\n\r\n";
        $signature = hash_hmac(
            'sha256',
            $data,
            $secret
        );

        $authorization = "hmac " . $apiKey . ":" . $milliseconds . ":" . $signature;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $hostname,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $http_verb,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: $authorization",
                "X-LLM-market: PH_MNL"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $decoded_response = json_decode($response);
        if ($status == 200) {
            $decoded_response->orderRef = $orderref;
        }
        return [
            "status" => $status,
            "data" => $decoded_response
        ];
    }
} /* END  OF CLASS */
