<?php

namespace App\Http\Resources\OrderResource;

use App\Http\Resources\ProductResource\Product as ProductResource;
use App\Http\Resources\OrderResource\ScdFood as ScdFoodResource;
use App\Http\Resources\OrderResource\PwdFood as PwdFoodResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItem extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $result = [
            'id' => $this->id,
            'pos_code' => $this->child_item_poscode,
            'product' => new ProductResource($this->product),
            'quantity' => $this->quantity,
            'gross_price' => number_format($this->item_price, 2),
            'net_price' => $this->item_basic_price,
        ];

        $scd = new ScdFoodResource($this->scd_food);

        if (isset($scd['scd_count'])) {
            $result['scd_count'] =  $scd['scd_count'];
        } else {
            $result['scd_count'] =  0;
        }

        if ($this->pwd_food != null) {
            $pwd =  new PwdFoodResource($this->pwd_food);
            $result['pwd_count'] =  $pwd['pwd_count'];
        } else {
            $result['pwd_count'] =  0;
        }
          
      
        return $result;

    }
}
