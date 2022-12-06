<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> 
</head>
<body>

<table>

        <p> Dear Sir / Ma'am, </p>
        
        <p>Thank you for ordering McDelivery! </p>

        <p> We received your order at {{$order_date[0]}} at {{$order_date[1]}} (Philippine Standard Time). Please expect your delivery to arrive at {{$delivery_time[0]}} at {{$delivery_time[1]}}  (Philippine Standard Time). <br><br></p>

        <p>Below is a breakdown of your order: </p>
        <table style="border-collapse:collapse;border:1px solid black;">
            <tr style="border:1px solid black;">

                    <th></th>
                    <th style="padding-left: 20px; padding-right: 20px;">ITEM</th>
                    <th style="padding-left: 20px; padding-right: 20px;">QTY</th>
                    <th style="padding-left: 20px; padding-right: 20px;">ITEM COST</th>
                    <th style="padding-left: 20px; padding-right: 20px;">ITEM TOTAL</th>
                
            </tr>
            <tbody>
                @php
                    $sub_total = 0;
                @endphp
            @foreach ($order_items as $order_item)        
            <tr>
                <td></td>
                <td style="padding-left: 20px; padding-right: 20px;">{{ $order_item->product->name }}</td>
                <td style="padding-left: 20px; padding-right: 20px;">{{ $order_item->quantity  }}</td>
                <td style="padding-left: 20px; padding-right: 20px;">{{sprintf('%0.2f', $order_item->product->gross_price)}}</td>
                <td style="padding-left: 20px; padding-right: 20px;">{{  sprintf('%0.2f', $order_item->product->gross_price) * $order_item->quantity }}</td>
                
                @php
                $sub_total +=  $order_item->product->gross_price * $order_item->quantity;
                @endphp        
               
            </tr>
            @endforeach
            <tr style="border-top:thick double #000000;"><td></td><td></td>
                <td style="text-align: center;"></td>
                <td style="text-align: right;">Subtotal</td>
                <td style="text-align: right;">{{$sub_total}}</td>
            </tr>
            <tr><td></td><td></td><td style="text-align: center;"></td>
                <td style="text-align: right;">Delivery Charge</td>
                <td style="text-align: right;">{{$delivery_charge}}</td>
            </tr>
            <tr><td></td><td></td><td style="text-align: center;"></td>
                <td style="text-align: right;font-weight:bolder;">Total Cost</td>
            <td style="text-align: right;font-weight:bolder;">{{sprintf('%0.2f',$sub_total + $delivery_charge)}}</td>
            </tr>
            </tbody>
        
         </table>   
        <p><br>PAYMENT TYPE: Cash on Delivery </p>

        <p>Your order number is <b>{{$order_id}} </b>You may check your order status by going to <a href='https://mcdelivery.com.ph/ordertracker?order_id={{$order_id}}'>https://mcdelivery.com.ph/ordertracker</a>

        <p>For orders with requested Senior Citizen or Persons with Disability discounts, a McDelivery representative will call you shortly. </p>

        <table>
                <tr>
                     <td style="padding:0;margin:0;line-height:1px;font-size:1px" align="center">
                         <span class="m"
                         style="font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:400;color:#c40514;text-align:left;text-decoration:none">
                             <a href="https://www.facebook.com/McDo.ph/" class="m_2026594159178999405small-copy"
                             style="text-decoration:none;border-style:none;border:0;padding:0;margin:0;font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:400;color:#c40514;text-align:left;text-decoration:none;font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:600;color:#c40514;text-align:left;text-decoration:none" target="_blank">Facebook</a>
                             &nbsp;|&nbsp;
                             <a href="https://twitter.com/McDo_PH" class="m"
                             style="text-decoration:none;border-style:none;border:0;padding:0;margin:0;font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:400;color:#c40514;text-align:left;text-decoration:none;font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:600;color:#c40514;text-align:left;text-decoration:none" target="_blank">Twitter</a>
                             &nbsp;|&nbsp;
                             <a href="https://www.instagram.com/mcdo_ph/?hl=en" style="text-decoration:none;border-style:none;border:0;padding:0;margin:0;font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:400;color:#c40514;text-align:left;text-decoration:none;font-family:HelveticaNeue,Helvetica Neue,Helvetica,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:600;color:#c40514;text-align:left;text-decoration:none" target="_blank" >Instagram</a>
                         </span>
                     </td>
                 </tr>
             </table>


<p>GADC assumes no liability for direct and/or indirect damages arising from the user's use of
    GADC's e-mail system and services.Users are solely responsible for the content they disseminate.GADC is not responsible
    for any third-party claim, demand, or damage arising out of use the GADC's e-mail systems or services.</p>
</body>
</html>