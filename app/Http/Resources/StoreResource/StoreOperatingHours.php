<?php

namespace App\Http\Resources\StoreResource;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreOperatingHours extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
       $days = ["", "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        return [
            "day_of_week" => $days[$this->day_of_week],
            "day"         => $this->day_of_week,
            "start_time"  => explode(" ", $this->to_time)[1],
            "end_time"    => explode(" ", $this->from_time)[1]
        ];
    }
}
