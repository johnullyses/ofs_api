<?php

namespace App\Http\Resources\NotificationResource;

use Illuminate\Http\Resources\Json\JsonResource;

class Notification extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id"      => $this->id,
            "user_id" => $this->user_id,
            "message" => $this->message,
            "script"  => $this->script,
            "is_read" => $this->is_read,
            "time"    => $this->time_since(time() - strtotime($this->created_at))
        ];
    }

    private function time_since($since) {
        $chunks = array(
            array(60 * 60 * 24 * 365 , 'year'),
            array(60 * 60 * 24 * 30 , 'month'),
            array(60 * 60 * 24 * 7, 'week'),
            array(60 * 60 * 24 , 'day'),
            array(60 * 60 , 'hour'),
            array(60 , 'minute'),
            array(1 , 'second')
        );
    
        for ($i = 0, $j = count($chunks); $i < $j; $i++) {
            $seconds = $chunks[$i][0];
            $name = $chunks[$i][1];
            if (($count = floor($since / $seconds)) != 0) {
                break;
            }
        }
        
        if ($name == "second") {
            return "now";
        } else {
            return ($count == 1) ? '1 '.$name : "$count {$name}s";
        }
    }
}
