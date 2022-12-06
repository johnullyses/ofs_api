<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);

date_default_timezone_set('Asia/Manila');

//Make sure that it is a POST request.
if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0) {
    // throw new Exception('Request method must be POST!');
    $error_response['status'] = 405;
    $error_response['error'] = "[Method Not Allowed] Request method must be POST";
    echo json_encode($error_response, JSON_PRETTY_PRINT);
    die();
}

//Make sure that the content type of the POST request has been set to application/json
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strcasecmp($contentType, 'application/json') != 0) {
    // throw new Exception('Content type must be: application/json');
    $error_response['status'] = 400;
    $error_response['error'] = "[Bad Request] Content type must be: application/json";
    echo json_encode($error_response, JSON_PRETTY_PRINT);
    die();
}

//Receive the RAW post data.
$content = trim(file_get_contents("php://input"));

//Attempt to decode the incoming RAW post data from JSON.
$post_data = json_decode($content, true);

//If json_decode failed, the JSON is invalid.
if (!is_array($post_data)) {
    // throw new Exception('Received content contained invalid JSON!');
    $error_response['status'] = 400;
    $error_response['error'] = "[Bad Request] Received content contained invalid JSON";
    echo json_encode($error_response, JSON_PRETTY_PRINT);
    die();
}

//Process the $post_data.
process_grab_webhook($post_data);

function process_grab_webhook($post_data)
{
    ofs_request('grab_delivery_status_changed', 'grabex', $post_data);
}

/**
 * [ofs_api description]
 * @param  [type] $action   [description]
 * @param  [type] $source   [description]
 * @param  [type] $contents [description]
 * @return [type]           [description]
 */
function ofs_request($action, $source, $contents)
{
    try {

        // Access Token
        $api_id = 'grab_webhook';
        $public_key     = '359a9bf30d8fae8051349075a786baf736ff4791744d2453e1bc558059b85f96';
        $private_key    = 'e7a8298baae6b6d74fa8631c42be73053523989a391bb658699570eeb0bba2e9';

        $timestamp = date('Y-m-d H:i:s');

        $content['data'] =  $contents;

        $content_body['data'] = array(
            'api_id' => $api_id,
            'api_key' => $public_key,
            'timestamp' => $timestamp,
            'source' => $source,
            'hostname' => "myhost",
            'action' => $action,
            'contents' => $content
        );

        // sign request data
        $content_body['signature'] = cds_signed_request(json_encode($content_body['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $private_key);

        $send_data = $content_body;

        $json_data = json_encode($send_data);

        // OFS API URL
        $url = 'http://192.168.10.32/api/grab-webhook/status-update';

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data),
            )
        );
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        $ofs_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            $error_response['status'] = 500;
            $error_response['error'] = "[Internal Server Error] OFS API Request encountered an error";
            echo json_encode($error_response, JSON_PRETTY_PRINT);
            die();
        }

        curl_close($curl);

        // header('Content-Type: application/json');
        echo $ofs_response;
    } catch (Exception $e) {
        $error_response['status'] = 500;
        $error_response['error'] = "[Internal Server Error] OFS API Request encountered an error";
        echo json_encode($error_response, JSON_PRETTY_PRINT);
        die();
    }
}

/**
 * Signed the request
 *
 * @return void
 */
function cds_signed_request($message, $private_key)
{
    $signature = base64_encode(hash_hmac('sha256', $message, $private_key, TRUE));
    return $signature;
}
