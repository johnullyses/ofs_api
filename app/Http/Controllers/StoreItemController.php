<?php

namespace App\Http\Controllers;

use App\Models\StoreItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mail;
use App\Mail\SendEmail;
use DB;
use App\Models\Product;
use App\Models\ProductItem;
class StoreItemController extends Controller
{
    private $StoreItem;
    private $ProductItem;
    private $Product;
    public function __construct(StoreItem $StoreItem,ProductItem $ProductItem,Product $Product)
    {
        $this->StoreItem = $StoreItem;
        $this->ProductItem = $ProductItem;
        $this->Product = $Product;
        
    }

    public function getItems($store_id)
    {
        $Products = $this->Product
        ->where('store_id',$store_id)
        ->orderBy('name', 'ASC')
        ->get();
        return json_encode($Products);
    }
    public function getItemsWithStatus($store_id,$status)
    {
        $Products = $this->Product
        ->where('store_id',$store_id)
        ->where('is_active',$status)
        ->orderBy('name', 'ASC')
        ->get();
        return json_encode($Products);
    }
    public function updateProductStatus($store_id,$ProductID,$status)
    {
        $Products = $this->Product
        ->where('store_id',$store_id)
        ->where('id',$ProductID)
        ->update(["is_active" => $status]);

        // $result = $this->StoreItem->where('store_id', $store_id)
        // ->where('id', $ProductID)
        // ->first(); 
       
        // $item_code = $result->item_code;
        // $item_code_status = $result->is_enable;  
        // $return = array(
        //     "item code"=>$item_code,
        //     "item_code_status"=>$item_code_status
        // );
        // $result = $this->ProductItem->where('item_code', $item_code)
        //                 ->where('store_id', $store_id)
        //                 ->get();
        // foreach($result as $row){
        //     $product_category = $row->category;
        //     $parent_pos_code  = $row->parent_pos_code;
        //     $child_poscode    = $row->child_product_poscode;
        //     if ($item_code_status == 1) {
        //         if (strtolower($product_category) == "main") {
        //             $this->deactivate_product(1, $child_poscode, $store_id);
        //             $this->count_main_composition($parent_pos_code, $store_id);
        //         } else {
        //             $this->deactivate_product(1, $child_poscode, $store_id);
        //         }
        //     } else {
        //         // item is inactive
        //         if (strtolower($product_category) == "main") {
        //             // item is a 'main' composition
        //             $this->deactivate_product(0, $child_poscode, $store_id);
        //             $this->deactivate_product(0, $parent_pos_code, $store_id);
        //         } else {
        //             // deactivate child
        //             $this->deactivate_product(0, $child_poscode, $store_id);
        //         }
        //     }
        // }       
        
    }
    function deactivate_product($is_active, $target_poscode, $store_id)
    {
        $product = Product::where(['pos_code' => $target_poscode, 'store_id' => $store_id])->first();

        $product->is_active = $is_active;
        $product->save();

    }

    function count_main_composition($parent_pos_code, $store_id)
    {
        $product_item  = ProductItem::where(['parent_pos_code' => $target_poscode, 'store_id' => $store_id, 'category' => 2, 'is_active' => 0])->get();
       
        if ($product_item) {
            
            foreach($product_item as $row){
                $ItemCode = $row->item_type_code;

                $item = StoreItem::where(['item_code' => $ItemCode, 'store_id' => $store_id])->first();
                $item_code_status = $item->is_enable;

                  // check if the item is active
                  if ($item_code_status == 1) {
                    // if active then enable parent product
                    $this->deactivate_product(1, $parent_poscode, $store_id);

                } else {
                    // disable parent product
                    $this->deactivate_product(0, $parent_poscode, $store_id);
                }
            }

        } else {
             // activate parent product
             $this->deactivate_product(1, $parent_poscode, $store_id);
        }

    }

    
}
