<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Store;
use Validator;

class ProductController extends Controller
{
    public function product_create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pos_code' => 'required',
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            foreach ($validator->messages()->getMessages() as $field_name => $messages) {
                // Go through each message for this field.
                foreach ($messages as $message) {
                    return [
                        "error" => true,
                        "message" => $message,
                        "data" => []
                    ];
                }
            }
        } else {
            $product = Product::where("store_id", 1)->where("pos_code", $request->pos_code)->count();
            if ($product == 0) {
                $stores = Store::select('id')->get();
                foreach ($stores as $store) {
                    $prod = new Product();
                    $prod->pos_code = $request->pos_code;
                    $prod->store_id = $store->id;
                    $prod->name = $request->name;
                    $prod->description = $request->description;
                    $prod->is_enable = 1;
                    $prod->is_active = 1;
                    $prod->product_type = 5;
                    $prod->gross_price = 0;
                    $prod->save();
                }
                return [
                    "error" => false,
                    "message" => "Success",
                    "data" => [
                        "pos_code" => $request->pos_code,
                        "name" => $request->name,
                    ]
                ];
            } else {
                return [
                    "error" => false,
                    "message" => "POS Code already exists",
                    "data" => [
                        "pos_code" => $request->pos_code,
                        "name" => $request->name,
                    ]
                ];
            }
        }
    }


    public function store_product_list(Request $request)
    {
        $products = Product::whereStoreId($request->store_id)
            ->orderBy('name', 'ASC')
            ->select("id", "store_id", "name", "pos_code", "basic_price", "is_active", "category")
            ->get();

        return [
            "status" => "success",
            "products" => $products
        ];
    }
}
