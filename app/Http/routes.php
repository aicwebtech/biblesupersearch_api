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

//Route::get('/', function() {
//    return view('docs.home');
//    //return view('welcome2');
//});

Route::get('/', 'DocumentationController')->middleware('https');

/* Routes for administrative backend */
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
Route::get('/auth/login', function () {
    return redirect('/admin');
});
Route::post('/auth/login', 'Auth\AuthController@postLogin');

Route::get('/auth/logout', 'Auth\AuthController@getLogout');
Route::get('/admin/main', 'AdminController@getMain');
//Route::controller('admin', 'AdminController');

/* Routes for the API  */
Route::get('/api/{action?}','ApiController@genericAction')->middleware('api'); // 'Action' defaults to 'query'
Route::post('/api/{action?}','ApiController@genericAction')->middleware('api'); // 'Action' defaults to 'query'

