<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReportResource\CancelledOrder as CancelledOrder;
use Illuminate\Http\Request;
use DB;

class HomepageController extends Controller
{

    public function getHomepage()
    {
        $res = [];

        $homepage = DB::table('homepage')->get();
        $category = DB::table('homepage_category')->get();

        $res['homepage'] = $homepage;
        $res['homepage_category'] = $category;
        $res['url'] = url('/')."/images";
        
        return response()->json($res);
    }

    public function getHomepageContents(Request $request)
    {
        $db = DB::table('homepage_contents')
                ->where('parent_id', $request->category_id)
                ->get();

        return response()->json($db);
    }
}
