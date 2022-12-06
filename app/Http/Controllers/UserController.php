<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth;
use App\Http\Resources\StoreResource\User as UserResource;
use Illuminate\Support\Arr;

class UserController extends Controller
{

    public function get_profile(Request $request) 
    {
        $user = User::with(['role', 'store'])->where("id", Auth::user()->id)->first();
        return $user;
    }

    public function login(Request $request)
    {

       
        if (filter_var($request->username, FILTER_VALIDATE_EMAIL)) {
            Auth::attempt(['email'=>$request->username, 'password'=>$request->password]);
        }

        if (Auth::check()) {
    
            $user = User::find(Auth::id());

            $access = $user->createToken(date('Y-m-d h:i:s').'-'.$user->email);

            return response()->json([
                'access_token' => $access->accessToken,
                'token_expiry' => $access->token->expires_at,
                'name' => $user->name,
                'email' => $user->email
            ]);
        } else {
            return response()->json([
                "error" => 'invalid_credentials'
            ]);
        }

        return 0;
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->token()->revoke();
            return 1;
        }

        return 0;
    }

    public function get_users()
    {
        $users = User::get(['id', 'store_id', 'name', 'role_id']);
        return UserResource::collection($users);
    }

    public function assign_user_to_store(Request $request, $storeid)
    {
        //var_dump($request->user); exit;
        $response = [];
        $usersStore = collect($request->user)
            ->map(function ($row) use ($storeid) {
                $obj = [
                    'id' => $row,
                    'store_id' => $storeid
                ];
                return Arr::only($obj, ['store_id', 'id']);
            });
        //var_dump($usersStore->all());
        //exit;

        $usersStore->each(function (array $row) use (&$response) {
            $hasRows = User::where('id', $row['id'])->count();

            if ($hasRows > 0) {
                $response = User::where('id', $row['id'])
                    ->update(['store_id' => $row['store_id']]);
            } else {
                $userstorelink = new User;
                $userstorelink->user_id = $row['id'];
                $userstorelink->store_id = $row['store_id'];
                $response = $userstorelink->save();
            }
            //return $response;
        });

        if ($response || ($response >= 0)) {
            return response(array("status" => 200, "message" => "User Assigned on Store Success"), 200);
        } else {
            return response(array("status" => 500, "error" => "User Store Assign Failed"), 500);
        }
    }

    public function userRoles(Request $request)
    {
        $roles = Role::all();
        return $roles;
    }

    public function updateUserRole(Request $request)
    {
        $user = User::find($request->payload['user_id']);
        $user->role_id = $request->payload['role_id'];
        $user->save();

        return response(array("status" => 200, "message" => "Updated"));
    }

}

?>