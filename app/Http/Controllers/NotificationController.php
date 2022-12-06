<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Resources\NotificationResource\Notification as NotificationResource;
use Auth;

class NotificationController extends Controller
{

    public function getNotifications(Request $request) 
    {
        $notifications = Notification::where("user_id", Auth::user()->id)
                                        ->orderBy('id', 'desc')
                                        ->limit(20)
                                        ->get();

        return NotificationResource::collection($notifications);
    }

    public function readNotification(Request $request) 
    {
        $notification = Notification::find($request->id);
        $notification->is_read = 1;
        $notification->save();

        return $notification;
    }
}

?>