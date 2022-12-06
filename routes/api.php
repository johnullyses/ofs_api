<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\OrderController;
use App\Http\Controllers\DeliveryBookingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CancelledReportController;
use App\Http\Controllers\DeliveryPerformanceController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HubDecController;
use App\Http\Controllers\StoreItemController;
use App\Http\Controllers\CustomerReceiveTimeController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\BillableSalesController;
use App\Http\Controllers\ProductMixController;
use App\Http\Controllers\StatusAnalyzeController;
use App\Http\Controllers\DivertedOrderController;
use App\Http\Controllers\StoreDashboardController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\StoreManagementController;
use App\Http\Controllers\StoreAssignmentController;
use App\Http\Controllers\CancelOrderController;
use App\Http\Controllers\ProductController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('order/place', [OrderController::class, 'order_place']);
Route::post('order/status', [OrderController::class, 'order_status']);
Route::post('order/item-availability', [OrderController::class, 'item_availability']);
Route::post('order/cancel', [OrderController::class, 'update_cancel_order']);
Route::get('order/cancel-reason-list', [OrderController::class, 'get_cancel_reasons']);

Route::POST('store/store-assignment', [StoreAssignmentController::class, 'assign_store']);

Route::post('product/create', [ProductController::class, 'product_create']);
Route::post('store/products', [ProductController::class, 'store_product_list']);

Route::post('grab-webhook/status-update', [DeliveryBookingController::class, 'grab_delivery_status_changed']);
Route::post('lalamove-webhook/status-update', [DeliveryBookingController::class, 'lalamove_delivery_status_changed']);

Route::middleware(['auth:api'])->group(function () {

    //Order Monitoring
    Route::get('store/{store}/order/monitoring', [OrderController::class, 'order_monitoring']);
    //Order Details
    Route::get('store/{store}/order/{id}/details', [OrderController::class, 'order_details']);
    //Order Update Status
    Route::post('store/{store}/order/{id}/acknowledge', [OrderController::class, 'acknowledge']);
    Route::post('store/{store}/order/{id}/rider-assign', [OrderController::class, 'rider_assign']);
    Route::post('store/{store}/order/{id}/rider-out', [OrderController::class, 'rider_out']);
    Route::post('store/{store}/order/{id}/rider-back', [OrderController::class, 'rider_back']);
    Route::post('store/{store}/order/{id}/order-close', [OrderController::class, 'order_close']);
    Route::post('store/{store}/order/{id}/received-order', [OrderController::class, 'update_order_receive_datetime']);
    //Order Threshold
    Route::post('store/{store}/order/order-threshold', [OrderController::class, 'order_threshold']);
    //Advance Order Notif
    Route::post('store/{store}/order/{id}/advance-order-notif', [OrderController::class, 'advanceOrderNotification']);
    
    //Hub Declaration
    Route::post('store/{store}/hub-declaration/create', [HubDecController::class, 'setHubDeclaration']);
    Route::get('store/{store}/hub-declaration/current', [HubDecController::class, 'getCurrentHubDeclaration']);
    Route::post('store/{store}/hub-declaration/validate-store-pin', [HubDecController::class, 'validateStorePin']);
    Route::get('store/{store}/hub-declaration/hold-schedule', [HubDecController::class, 'holdSchedule']);

    //Store Operating Hours
    Route::get('store/{store}/hub-declaration/store-operating-hours', [HubDecController::class, 'getStoreOperatingHours']);
    Route::post('store/{store}/hub-declaration/store-operating-hours', [HubDecController::class, 'setStoreOperatingHours']);

    //Store Holidays
    Route::post('store/{store}/hub-declaration/create-holiday', [HubDecController::class, 'createHoliday']);
    Route::post('store/{store}/hub-declaration/update-holiday', [HubDecController::class, 'updateHoliday']);
    Route::post('store/{store}/hub-declaration/delete-holiday', [HubDecController::class, 'deleteHoliday']);

    Route::get('store/{store}/hub-dec-list', [HubDecController::class, 'gethubDecList']);
    //Booking
    Route::get('delivery/carriers', [DeliveryBookingController::class, 'get_carriers']);
    Route::post('delivery/{carrier}/{action}', [DeliveryBookingController::class, 'delivery']);

    //Notifications
    Route::get('notifications', [NotificationController::class, 'getNotifications']);
    Route::post('notifications/read', [NotificationController::class, 'readNotification']);

    //user
    Route::get('user', [UserController::class, 'get_profile']);
    Route::post('logout', [UserController::class, 'logout']);

    //Cancelled Order Report
    Route::post('store/{store}/order/cancelled-order', [CancelledReportController::class, 'cancelledOrders']);
    Route::post('store/{store}/sources', [CancelledReportController::class, 'orderSources']);

    //Delivery Performance
    Route::get('delivery/{store}', [DeliveryPerformanceController::class, 'getReport']);
    Route::get('delivery/{store}/{startDate}/{endDate}/{promisedtime}', [DeliveryPerformanceController::class, 'getReportWithDate']);

    //Store Items / Producct Availability
    Route::get('store/items/{store}', [StoreItemController::class, 'getItems']);
    Route::get('store/items/{store}/{status}', [StoreItemController::class, 'getItemsWithStatus']);
    Route::get('store/items/{store}/{product}/{status}', [StoreItemController::class, 'updateProductStatus']);

    //Sales Report
    Route::post('sales/{store}', [SalesController::class, 'getSales']);

    //Customer Receive Time Report
    Route::post('store/{store}/customer-receive-time', [CustomerReceiveTimeController::class, 'customerReceiveTime']);
    //Billable Report
    Route::post('store/{store}/billable', [BillableSalesController::class, 'billableSales']);


    //Product Mix
    Route::get('store/product-mix/{store}', [ProductMixController::class, 'getProductMix']);
    Route::get('store/product-mix/list-products/{store}', [ProductMixController::class, 'getProductsList']);
    Route::get('store/product-mix/list-sources/{store}', [ProductMixController::class, 'getSourcesList']);
    Route::get('store/product-mix/filter/{store}/{start}/{end}/{source}/{product}', [ProductMixController::class, 'getProductMixWithFilter']);


    //Status Analyze Report
    Route::post('store/{store}/status-analyze', [StatusAnalyzeController::class, 'getStatusAnalyze']);


    //Homepage Data
    Route::post('store/homepage', [HomepageController::class, 'getHomepage']);
    Route::post('store/homepage/contents', [HomepageController::class, 'getHomepageContents']);

    //Diverted Order Report
    Route::post('diverted-orders/{store}', [DivertedOrderController::class, 'getDivertedOrders']);

    //Store Dashboard
    Route::get('store/{store}/dashboard', [StoreDashboardController::class, 'storeDashboard']);
    
    //Change Password
    Route::post('store/changePassword/{store}/change', [ChangePasswordController::class, 'changePassword']);

    //Store Management
    Route::get('store/manage', [StoreManagementController::class, 'storeManage']);
    Route::get('store/{store}/detail', [StoreManagementController::class, 'storeDetails']);
    Route::get('store/{store}/detail-sources', [StoreManagementController::class, 'storeDetailSources']);
    Route::get('store/provinces', [StoreManagementController::class, 'getProvinces']);
    Route::get('store/provinces/{province_id}/cities', [StoreManagementController::class, 'getCities']);
    Route::post('store/detail/create', [StoreManagementController::class, 'createStoreDetails']);
    Route::post('store/{store}/detail/update', [StoreManagementController::class, 'updateStoreDetails']);

    //Cancel Order
    Route::get('store/{store}/order/cancel_reasons', [OrderController::class, 'get_cancel_reasons']);

    //User Store Link
    Route::get('users', [UserController::class, 'get_users']);
    Route::post('store/{store}/user-store-link', [UserController::class, 'assign_user_to_store']);
    Route::get('user-roles', [UserController::class, 'userRoles']);
    Route::post('user-role/update', [UserController::class, 'updateUserRole']);
});


Route::post('login', [UserController::class, 'login']);
