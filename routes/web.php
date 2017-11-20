<?php

Route::group(['prefix' => 'laravel/merchant','middleware' => 'api'],function(){

Route::get('/', function () {
    return view('index');
});

Route::get('/welcome', function () {
    return view('welcome');
});

Route::get('/clear-cache', function() {
    $exitCode = Artisan::call('cache:clear');
    return Response::json(array('success' => true ,'code'=> $exitCode));
});


});



Route::any('{catchall}', function() {
return View::make('index');

})->where('catchall', '.*');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


