<?php

namespace App\Http\Resources\OrderResource;

use App\Http\Resources\CustomerResource\Customer as CustomerResource;
use App\Http\Resources\CustomerResource\Address as DeliveryAddressResource;
use App\Http\Resources\OrderResource\OrderItem as OrderItemResource;
use App\Http\Resources\OrderResource\OrderNote as OrderNoteResource;
use App\Http\Resources\StoreResource\Store as StoreResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetails extends JsonResource
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
            'order_pin' => $this->order_pin,
            'customer' => new CustomerResource($this->customer),
            'delivery_address' => new DeliveryAddressResource($this->delivery_address),
            'order_date' => date('Y-m-d H:i:s', strtotime($this->order_date)),
            'is_advance_order' => $this->is_advance_order,
            'is_view' => $this->is_view,
            'is_edited' => $this->is_edited,
            'store' => new StoreResource($this->store),
            // 'customer_id' => $this->customer_id,
            'agent_name' => $this->user_name,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'source_id' => $this->source_id,
            'source_text' => $this->source_text,
            'payment_id' => $this->payment_id,
            'payment_text' => $this->payment_text,
            'city_name' => $this->city_name,
            'order_remarks' => !$this->order_remarks ? '' : $this->order_remarks,
            'total_net' => $this->total_net,
            'total_w_vat' => $this->total_w_vat,
            'total_gross' => $this->total_gross,
            'change_amount' => $this->change_amount,
            'tendered_amount' => $this->tendered_amount,
            'voucher_amount' => $this->change_amount,
            'delivery_date' => date('Y-m-d H:i',strtotime(date($this->order_date) . ' + ' . $this->promised_time . ' minutes')),
            'rider_name' => !$this->rider_name ? '' : $this->rider_name,
            'dispatcher' => !$this->acknowledged_by ? '' : $this->acknowledged_by,
            'delivery_charge' => $this->delivery_charge,
            'order_items' => OrderItemResource::collection($this->order_items),
            'print_counter' => $this->print_counter,
            'received_datetime' => $this->received_datetime,
            'acknowledged_datetime' => $this->acknowledged_datetime,
            'rider_assigned_datetime' => $this->rider_assigned_datetime,
            'rider_out_datetime' => $this->rider_out_datetime,
            'rider_back_datetime' => $this->rider_back_datetime,
            'customer_receive_datetime' => $this->customer_receive_datetime,
            'status' => $this->status,
            'user_name' => $this->user_name,
            'updated_by' => $this->update_by,
            'created_datetime' => $this->created_datetime,
            'scd_id' => $this->scd_id,
            'scd_name' => $this->scd_name,
            'total_discounts' => $this->total_discounts,
            'excess_vat' => $this->excess_vat,
            'is_scd' => $this->is_scd,
            'is_pwd' => $this->is_pwd,
            'pwd_id' => $this->pwd_id,
            'pwd_name' => $this->pwd_name,
            'promised_time' => $this->promised_time,
            'order_notes' => OrderNoteResource::collection($this->order_notes),
            'advance_order_delivery_datetime' => $this->advance_order_delivery_datetime,
            'view_advance' =>$this->view_advance,
            'delivery_service' => $this->delivery_service,
            'service_method_id' => $this->service_method_id,
            'delivery_booking' => $this->delivery_bookings 
        ];

        if(isset($this->proximity)){
          $result['additional'] = $this->proximity;
        }


        return $result;
    }
}
