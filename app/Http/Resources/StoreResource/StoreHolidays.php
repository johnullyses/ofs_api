<?php

namespace App\Http\Resources\StoreResource;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreHolidays extends JsonResource
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
            "id" => $this->id,
            "date" => date("F j, Y", strtotime($this->from_time)),
            "date_y_m_d" => date("Y-m-d", strtotime($this->from_time)),
            "start_time"  => explode(" ", $this->to_time)[1],
            "end_time"    => explode(" ", $this->from_time)[1]
        ];
    }
}
