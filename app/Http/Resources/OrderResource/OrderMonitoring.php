<?php

namespace App\Http\Resources\OrderResource;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderResource\OrderItem as OrderItem;
use App\Http\Resources\CustomerResource\Customer as CustomerResource;

class OrderMonitoring extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        date_default_timezone_set("Asia/Manila");

        $result = [
            'id'               => $this->id,
            'customer'          => new CustomerResource($this->customer),
            'order_pin'        => $this->order_pin,
            'status_id'        => $this->status,
            'status_text'      => $this->status_text,
            'price'            => $this->total_gross,
            'change_for'       => $this->tendered_amount,
            'received_at'      => $this->received_datetime,
            'order_date'       => $this->order_date,
            // 'order_date_text'  => date_format(date_create($this->order_dated),"F d, Y h:i:s"),
            'view_status'      => $this->is_view,
            'is_editing'       => $this->is_editing,
            'edit_count'       => $this->is_edited,
            'is_advance'       => $this->is_advance_order,
            'payment_id'       => $this->payment_id,
            'bank_transfer_payment_confirmed' => $this->bank_transfer_payment_confirmed,
            'service_method_id' => $this->service_method_id,
            'service_method_text' => ($this->service_method_id == 1 ? 'delivery' : 'pickup' ),
            'delivery_booking' => $this->delivery_bookings 
        ];
        
        $order_items = OrderItem::collection($this->order_items);
        $order_items_temp = [];
        foreach ($order_items as $item) {
            $order_items_temp[] = [
                "name"     => $item->product->name,
                "quantity" => $item->quantity
            ];
        }

        $result['order_items'] = $order_items_temp;

        // advance order fields
        if ($this->is_advance_order == 1) {
            $result['ao_text'] = "ADVANCE";

            $date_now   = strtotime(date('Y-m-d H:i:s')); // date-time now
            $order_date = strtotime($this->order_date); // order_date
            $lapsed     = round(($date_now - $order_date) / 60, 0); // calculate advance order time remaining

            if ($lapsed >= 0) {
                $result['advance_ready'] = "YES";
            } else {
                $result['advance_ready'] = "NO";
            }

        } else {
            $result['advance_ready'] = "-";
            $result['ao_text']       = "";

        }

        $status_id = array(1, 2, 3, 4, 7, 8); // status with lapsed time

         // running lapse
         if (in_array($this->status, $status_id)) {
            // compute running lapsed
            $date_now              = strtotime(date('Y-m-d H:i:s')); // date-time now
            $order_date            = strtotime($this->order_date); // order_date
            $result['running_lapsed'] = round(($date_now - $order_date) / 60, 0);

        } else {
            // don't compute
            $result['running_lapsed'] = "-";
        }
        
        return $result;
    }
}

?>