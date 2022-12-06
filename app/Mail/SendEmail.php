<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Config;

class SendEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

     public $sub;
     public $mes;
     public $order_items;
     public $status;
     public $order_date;
     public $delivery_time;
     public $delivery_charge;

    public function __construct($subject, $messages, $order_items, $status, $date_order, $delivery_time_arr, $delivery_charges)
    {
        $this->sub = $subject;
        $this->mes = $messages;
        $this->order_items = $order_items;
        $this->status = $status;
        $this->order_date=$date_order; 
        $this->delivery_time = $delivery_time_arr;
        $this->delivery_charge = $delivery_charges;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = $this->sub;
        $order_id = $this->mes;
        $order_items = $this->order_items;
        $status = $this->status;
        $order_date = $this->order_date;
        $delivery_time = $this->delivery_time;
        $delivery_charge = $this->delivery_charge;
        

       switch ($status) {
           case 2:
                   $this->from(Config::get('app.ofs_notification_mail_address'))
                        ->view('email/sendemail', compact("order_id"),
                                                         ("order_items"),
                                                         ("order_date"),
                                                         ("delivery_time"),
                                                         ("delivery_charge"))
                        ->subject($subject);
               break;

           case 8:
                   $this->from(Config::get('app.ofs_notification_mail_address'))
                        ->view('email/raider_asigned', compact("order_id"))
                        ->subject($subject);
                break;
                
            case 3:
                   $this->from(Config::get('app.ofs_notification_mail_address'))
                        ->view('email/rider_out', compact("order_id"))
                        ->subject($subject);
                break;    
           
           default:
               # code...
               break;
       }
        
   
    }
}
