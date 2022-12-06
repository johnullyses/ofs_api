<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mail;
use DB;
use App\Models\User;
use Auth;
use Hash;
class ChangePasswordController extends Controller
{

    public function __construct()
    {
    
    }
    public function changePassword(Request $request,$Store)
    {
        $msg="";
        $currentPassword = $request->currentPassword;
        $newPassword = $request->newPassword;
        $user = User::find($Store);
        $status=false;
        if(!Hash::check($currentPassword, $user->password)){
            $status=true;
            $msg="Invalid Current Password Given";
            $code=400;
        }else{
            $newPassword=Hash::Make($newPassword);
            $userUpdate=DB::table('users')
            ->where('store_id',$Store)
            ->update(['password' => $newPassword]);
            if($userUpdate){
                $status=false;
                $code=200;
                $msg="Password Changed";
            }else{
                $error=true;
                $code=400;
                $msg="Password not Changed";
            }
        
        }  
        return response(array("code"=>$code, "error"=>$status,"msg"=>$msg), 200);
      
    }
}
