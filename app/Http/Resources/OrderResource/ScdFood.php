<?php

namespace App\Http\Resources\OrderResource;

use Illuminate\Http\Resources\Json\JsonResource;

class ScdFood extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return [
            'scd_count' => $this->scd_count,
        ];
    }
}
