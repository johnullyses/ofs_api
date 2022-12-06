<?php 

use config;

class Rebrandly {

	public function __construct()
	{
        $this->ci =& get_instance();
        $this->ci->config->load('config', TRUE);
        $this->ci->config->load('loginext_config', TRUE);

        // GrabEx API URL
        $this->api_key = config('rebrandly.rebrandly_api_key');
        // Client Credentials
        $this->domain = config('rebrandly.rebrandly_domain');
        $this->api_url = config('rebrandly.rebrandly_api_url');
        
        $this->loginext_tracker_url = config('loginext.tracker_url');
        $this->loginext_token = config('loginext.token');

    }

    function generate_short_link($order_no)
    {
    
      // reference: https://developers.rebrandly.com/docs/api-custom-url-shortener
    
      // request parameters
      $data = [
        'destination' => $this->loginext_tracker_url . "/track/#/order?ordno=$order_no&aid=" . $this->loginext_token,
          'domain' => [
            'fullName' => $this->domain,
          ]
      ];
    
      $ch = curl_init();
    
      curl_setopt($ch, CURLOPT_URL, $this->api_url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_POST, 1);
    
      $headers = array();
      $headers[] = 'Content-Type: application/json';
      $headers[] = 'Apikey: ' . $this->api_key;
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
      $result = curl_exec($ch);
      if (curl_errno($ch)) {
          echo 'Error:' . curl_error($ch);
      }  
      
      $decoded_response = json_decode($result, true);

      return $decoded_response;
    
      curl_close ($ch);
    
    
      /*
      [sample response]
    
      {
        "id": "174d77466da44dccb4ee7165fe49f169",
        "title": "Link f0c27",
        "slashtag": "f0c27",
        "destination": "https://www.youtube.com/channel/UCHK4HD0ltu1-I212icLPt3g",
        "createdAt": "2020-12-17T01:24:17.000Z",
        "updatedAt": "2020-12-17T01:24:17.000Z",
        "status": "active",
        "tags": [],
        "clicks": 0,
        "isPublic": false,
        "shortUrl": "rebrand.ly/f0c27",
        "domainId": "8f104cc5b6ee4a4ba7897b06ac2ddcfb",
        "domainName": "rebrand.ly",
        "domain": {
          "id": "8f104cc5b6ee4a4ba7897b06ac2ddcfb",
          "ref": "/domains/8f104cc5b6ee4a4ba7897b06ac2ddcfb",
          "fullName": "rebrand.ly",
          "sharing": {
            "protocol": {
              "allowed": [
                "http",
                "https"
              ],
              "default": "https"
            }
          },
          "active": true
        },
        "https": true,
        "favourite": false,
        "creator": {
          "id": "af59aed6002147f3a7b9165086fb022a",
          "fullName": "NiÃ±o Calamaya",
          "avatarUrl": "https://s.gravatar.com/avatar/605292a367e2b93e522e763ce7e308d5?size=80&d=retro&rating=g"
        },
        "integrated": false
      }
      */
    
    }
    
    
    function delete_short_link($id)
    {

        // reference: https://developers.rebrandly.com/docs/delete-a-link

        if (empty($id)) { die('No id to delete'); }

        $url = $this->api_url.'/'.$id;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        $headers = array();
        $headers[] = 'Apikey: ' . $this->api_key;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        // echo $result;

        curl_close ($ch);

        /*
        [sample response]
        {
            "id": "1adff09a5e6e45508fd99dfc9e62457b",
            "title": "Link f7s9r",
            "slashtag": "f7s9r",
            "destination": "https://www.youtube.com/channel/UCHK4HD0ltu1-I212icLPt3g",
            "createdAt": "2020-12-17T02:05:44.000Z",
            "updatedAt": "2020-12-17T02:05:44.000Z",
            "status": "deleted",
            "tags": [],
            "clicks": 1,
            "sessions": 1,
            "lastClickDate": "2020-12-17T02:17:20Z",
            "lastClickAt": "2020-12-17T02:17:20Z",
            "isPublic": false,
            "shortUrl": "rebrand.ly/f7s9r",
            "domainId": "8f104cc5b6ee4a4ba7897b06ac2ddcfb",
            "domainName": "rebrand.ly",
            "domain": {
            "id": "8f104cc5b6ee4a4ba7897b06ac2ddcfb",
            "ref": "/domains/8f104cc5b6ee4a4ba7897b06ac2ddcfb",
            "fullName": "rebrand.ly",
            "sharing": {
                "protocol": {
                "allowed": [
                    "http",
                    "https"
                ],
                "default": "https"
                }
            },
            "active": true
            },
            "https": true,
            "favourite": false,
            "creator": {
            "id": "af59aed6002147f3a7b9165086fb022a",
            "fullName": "NiÃ±o Calamaya",
            "avatarUrl": "https://s.gravatar.com/avatar/605292a367e2b93e522e763ce7e308d5?size=80&d=retro&rating=g"
            },
            "integrated": false
        }
        */

    }
} /* END  OF CLASS */
