<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

/* Routes for the API  */
Route::get('/api/{action?}' , 'ApiController@genericAction')->middleware('api'); // 'Action' defaults to 'query'
Route::post('/api/{action?}', 'ApiController@genericAction')->middleware('api'); // 'Action' defaults to 'query'

/* Route for Documentation UI */
Route::get('/', 'DocumentationController')->middleware('https');
Route::get('/documentation', 'DocumentationController')->middleware('https');



/* EVERYTHING BELOW IS EXPERIMENTAL, NON-PRODUCTION CODE */

//Route::get('/', function() {
//    return view('docs.home');
//    //return view('welcome2');
//});

/* Routes for (administrative) backend */
Route::get('/admin', function() {
    if(Auth::check()) {
        return redirect('/admin/main');
    }

    return view('admin.login');
});

Route::get('/admin/login', function() {
    return redirect('/admin');
});

//Route::get('/admin/login', 'Auth\AuthController@getLogin');
//Route::get('/auth/login', function () {
//    return redirect('/admin');
//});

//Route::post('/auth/login', 'Auth\AuthController@postLogin');
Route::get('/login', 'Auth\AuthController@viewLogin')->name('login');
Route::post('/login', 'Auth\AuthController@login');
Route::get('/auth/login', 'Auth\AuthController@viewLogin');
Route::post('/auth/login', 'Auth\AuthController@login');
Route::get('/logout', 'Auth\AuthController@logout')->name('logout');
Route::get('/landing', 'Auth\AuthController@landing')->name('auth.landing')->middleware('auth');
Route::get('/auth/reset', 'Auth\PasswordController@showLinkRequestForm')->name('password.request');
//Route::get('/auth/reset', 'Auth\PasswordController@showResetForm')->name('password.request');
Route::post('/auth/reset', 'Auth\PasswordController@sendResetLinkEmail')->name('password.email');
Route::get('/auth/change', 'Auth\PasswordController@showResetForm')->name('password.reset');
Route::post('/auth/change', 'Auth\PasswordController@reset');
Route::post('/auth/success', 'Auth\PasswordController@success');
Route::get('/admin/main', 'AdminController@getMain');
//Route::controller('admin', 'AdminController');

