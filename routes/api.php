<?php

use Illuminate\Http\Request;
use App\Http\Controllers\RedeemController;
use App\Http\Controllers\CustomerController;


Route::group([
				'middleware' => ['cors'],
				'prefix' => 'v1'], function() {

	// since we will be using this just for CRUD, we won't need create and edit
	// Angular will handle both of those forms
	// this ensures that a user can't access api/create or api/edit when there's nothing there
	Route::get('redeem/sendEmail','RedeemController@sendEmail' );
	Route::get('redeem/sendMessage','RedeemController@sendMessage' );
	Route::post('users/authenticate','UsersController@authenticate');
	Route::resource('redeem','RedeemController');
	Route::resource('accrueredeem','NOMAccrueRedeemController');
	Route::resource('customer.merchant','CustomerController');
	Route::get('cashpoints/merchant/{id}/report/{report_type}/{report_date}','CashpointsController@getReportData');
	Route::get('cashpoints/merchant/{id}','CashpointsController@showMerchantCashpoints');
		
	Route::resource('cashpoints/customer.merchant','CashpointsController@showCustomerCashpoints');

	Route::get('generatePDF', 'CashpointsController@generatePDF'  );

//password reset ...

	Route::post('users/password/reset','UsersController@resetpassword');


/*
	Route::get('pdftest', function () {

    Fpdf::AddPage();
    Fpdf::SetFont('Courier', 'B', 18);
    Fpdf::Cell(50, 25, 'Hello World!');
    Fpdf::Output();

}); */


});


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!


|Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


*/








