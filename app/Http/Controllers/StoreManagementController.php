<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource\Store as StoreResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class StoreManagementController extends Controller
{
    private $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function storeManage()
    {
        $stores = $this->store
            ->select('id as store_id', 'code as store_code', 'store_name', 'is_active', 'hostname', 'city')
            ->orderBy('store_name', 'ASC')
            ->get();
        return StoreResource::collection($stores);
    }

    public function storeDetails(Request $request, $store_id)
    {
        $store = $this->store
            ->select('id as store_id', 'code', 'store_name', 'hostname', 'address', 'brand_id', 'province', 'city', 'profit_center_type', 'region', 'region_type', 'store_type', 'contact_numbers', 'contact_emails', 'remarks', 'x', 'y', 'receipt_message', 'delivery_number', 'hub_dec_pin', 'is_active', 'is_24hours', 'is_grab_express', 'is_mds_rider', 'printer_ip_address', 'paymaya_public_token', 'paymaya_secret_token', 'gcash_qr_code')
            ->where("id", $store_id)
            ->get();
        return new StoreResource($store);
    }

    public function storeDetailSources()
    {
        $result = [];
        $brands = DB::table('ofs_brands')
            ->get();

        $pc_type = DB::table('ofs_profit_center_type')
            ->get();

        $store_region = DB::table('ofs_store_region')
            ->get();

        $store_region_type = DB::table('ofs_store_region_type')
            ->get();

        $store_type = DB::table('ofs_store_type')
            ->get();

        $result['brands'] = $brands;
        $result['pc_type'] = $pc_type;
        $result['store_region'] = $store_region;
        $result['store_region_type'] = $store_region_type;
        $result['store_type'] = $store_type;

        return $result;
    }

    public function getProvinces()
    {
        $provinces = DB::table('ofs_provinces')
            ->orderBy('province_name', 'ASC')
            ->get();

        if ($provinces->isEmpty()) {
            return response(array("status" => 500, "error" => "No Provinces Found"), 500);
        }
        return $provinces;
    }

    public function getCities($province_id)
    {
        $cities = DB::table('ofs_municipalities')
            ->where('province_id', $province_id)
            ->orderBy('municipality_name', 'ASC')
            ->get();

        if ($cities->isEmpty()) {
            return response(array("status" => 500, "error" => "No Cities Found"), 500);
        }

        return $cities;
    }

    public function createStoreDetails(Request $request)
    {
        try {
            $store = new Store();

            $store->code = $request->code;
            $store->store_name = $request->store_name;
            $store->hostname = $request->hostname;
            $store->address = $request->address;
            $store->province = $request->province;
            $store->city = $request->city;
            $store->profit_center_type = $request->profit_center_type;
            $store->region = $request->region;
            $store->region_type = $request->region_type;
            $store->store_type = $request->store_type;
            $store->contact_numbers = $request->contact_numbers;
            $store->contact_emails = $request->contact_emails;
            $store->remarks = $request->remarks;
            $store->x = $request->x;
            $store->y = $request->y;
            $store->receipt_message = $request->receipt_message;
            $store->delivery_number = $request->delivery_number;
            $store->hub_dec_pin = $request->hub_dec_pin;
            $store->is_active = (int) $request->is_active;
            $store->is_24hours = (int) $request->is_24hours;
            $store->is_grab_express = (int) $request->is_grab_express;
            $store->is_mds_rider = (int) $request->is_mds_rider;
            $store->brand_id = $request->brand_id;
            $store->printer_ip_address = $request->printer_ip_address;
            $store->paymaya_public_token = $request->paymaya_public_token;
            $store->paymaya_secret_token = $request->paymaya_secret_token;
            $store->gcash_qr_code = $request->gcash_qr_code;

            $store->datetime_created = date('Y-m-d H:i:s');
            $store->save();
            $new_store_id = $store->id;
            //copy store id 1 products to new store
            $products = Product::where("store_id", 1)->get();
            $to_inserts = [];
            foreach ($products as $product) {
                $to_inserts[] = [
                    'is_template'           => $product->product,
                    'store_id'              => $new_store_id,
                    'name'                  => $product->name,
                    'description'           => $product->description,
                    'pos_code'              => $product->pos_code,
                    'item_code'             => $product->item_code,
                    'basic_price'           => $product->basic_price,
                    'gross_price'           => $product->gross_price,
                    'default_start_date'    => $product->default_start_date,
                    'default_end_date'      => $product->default_end_date,
                    'promo_gross_price'     => $product->promo_gross_price,
                    'promo_basic_price'     => $product->promo_basic_price,
                    'promo_date_start'      => $product->promo_date_start,
                    'promo_date_end'        => $product->promo_date_end,
                    'category'              => $product->category,
                    'product_type'          => $product->product_type,
                    'menu_id'               => $product->menu_id,
                    'on_promo'              => $product->on_promo,
                    'is_enable'             => $product->is_enable,
                    'is_active'             => $product->is_active,
                    'discountable'          => $product->discountable,
                    'datetime_created'      => $product->datetime_created,
                    'datetime_updated'      => $product->datetime_updated,
                    'promo_tax'             => $product->promo_tax,
                    'basic_tax'             => $product->basic_tax,
                    'created_by'            => $product->created_by,
                    'last_updated_by'       => $product->last_updated_by,
                ];
            }
            DB::table('ofs_products')->insert($to_inserts);
            return new StoreResource($store);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            $result['status']  = 500;
            $result['error'] = "Could not create new Store.";
            return $result;
        }
    }

    public function updateStoreDetails(Request $request, $store_id)
    {
        try {
            $store = Store::where(['id' => $store_id])->first();

            $store->code = $request->code;
            $store->store_name = $request->store_name;
            $store->hostname = $request->hostname;
            $store->address = $request->address;
            $store->province = $request->province;
            $store->city = $request->city;
            $store->profit_center_type = $request->profit_center_type;
            $store->region = $request->region;
            $store->region_type = $request->region_type;
            $store->store_type = $request->store_type;
            $store->contact_numbers = $request->contact_numbers;
            $store->contact_emails = $request->contact_emails;
            $store->remarks = $request->remarks;
            $store->x = $request->x;
            $store->y = $request->y;
            $store->receipt_message = $request->receipt_message;
            $store->delivery_number = $request->delivery_number;
            $store->hub_dec_pin = $request->hub_dec_pin;
            $store->is_active = (int) $request->is_active;
            $store->is_24hours = (int) $request->is_24hours;
            $store->is_grab_express = (int) $request->is_grab_express;
            $store->is_mds_rider = (int) $request->is_mds_rider;
            $store->brand_id = $request->brand_id;
            $store->printer_ip_address = $request->printer_ip_address;
            $store->paymaya_public_token = $request->paymaya_public_token;
            $store->paymaya_secret_token = $request->paymaya_secret_token;
            $store->gcash_qr_code = $request->gcash_qr_code;

            $store->datetime_updated = date('Y-m-d H:i:s');


            $store->save();

            return new StoreResource($store);
        } catch (ModelNotFoundException $ex) {
            Log::error($ex->getMessage());
            $result['status']  = 500;
            $result['error'] = "Store not found.";
            return $result;
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            $result['status']  = 500;
            $result['error'] = "Could not update Store Details.";
            return $result;
        }
    }
}
