<?php

namespace App\Http\Resources\CustomerResource;

use Illuminate\Http\Resources\Json\JsonResource;

class Address extends JsonResource
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
          'id' => $this->id,
          'order_id' => $this->order_id,
          'address_id' => $this->address_id,
          'address' => $this->address,
          'contact_number' => $this->contact_number,
          'company_name' => $this->company_name,
          'barangay' => $this->barangay,
          'building_name' => $this->building_name,
          'floor_dept_house_no' => $this->floor_dept_house_no,
          'landmark_1' => !$this->landmark_1 ? '': $this->landmark_1,
          'province' => $this->province,
          'remarks' => $this->remarks,
          'area_subd_district' => $this->area_subd_district,
          'street' => $this->street,
          'city_municipality' => $this->city_municipality,
          'restaurant_id' => $this->restaurant_id,
          'rta_id' => $this->rta_id,
          'region' => $this->region,
          'country' => $this->country,
          'point_x' => $this->addressDetails->point_x,
          'point_y' => $this->addressDetails->point_y
        ];
    }
}
