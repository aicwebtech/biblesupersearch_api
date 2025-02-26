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

$api_middleware = env('APP_ENV', 'production') == 'local' ? 'api_testing' : 'api';

// 'api_testing' middleware has higher access rate allowance, for testing purposes

/* Routes for the API  */
Route::get('/api/{action?}' , 'ApiController@genericAction')->middleware($api_middleware); // 'Action' defaults to 'query'
Route::post('/api/{action?}', 'ApiController@genericAction')->middleware($api_middleware); // 'Action' defaults to 'query'

/* Route for Documentation UI */
Route::get('/', 'DocumentationController')->name('docs');
Route::get('/documentation', 'DocumentationController');



/* EVERYTHING BELOW PERTAINS TO BACKEND ADMINISTRATION */


/* Routes for (administrative) backend */
Route::get('/admin', function() {
    if(Auth::check()) {
        return redirect('/admin/bibles');
        // return redirect('/admin/main'); // todo - make dashboard!
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
Route::get('/auth/success', 'Auth\PasswordController@success');

Route::get('/admin/main', 'AdminController@getMain')->name('admin.main');
Route::get('/admin/help', 'AdminController@help')->name('admin.help');
Route::get('/admin/debug', 'AdminController@debug')->name('admin.debug');
Route::get('/admin/update', 'AdminController@softwareUpdate')->name('admin.update')->middleware('install');
Route::get('/admin/uninstall', 'AdminController@uninstallPage')->name('admin.uninstall')->middleware('install');
Route::post('/admin/uninstall', 'AdminController@softwareUninstall')->name('admin.douninstall');
Route::get('/admin/uninstalled', 'AdminController@uninstalled')->name('admin.uninstalled'); //->middleware('install');
Route::get('/admin/phpinfo', 'AdminController@debug')->name('admin.phpinfo'); 

Route::get('/admin/bibles/grid', 'Admin\BibleController@grid');
Route::get('/admin/bibles/languages', 'Admin\BibleController@languages');
Route::get('/admin/bibles/copyrights', 'Admin\BibleController@copyrights');
Route::get('/admin/bibles/edit', 'Admin\BibleController@editHash');
Route::post('/admin/bibles/enable/{id}', 'Admin\BibleController@enable');
Route::post('/admin/bibles/disable/{id}', 'Admin\BibleController@disable');
Route::post('/admin/bibles/install/{id}', 'Admin\BibleController@install');
Route::post('/admin/bibles/uninstall/{id}', 'Admin\BibleController@uninstall');
Route::post('/admin/bibles/research/{id}', 'Admin\BibleController@research');
Route::post('/admin/bibles/unresearch/{id}', 'Admin\BibleController@unresearch');
Route::post('/admin/bibles/export/{id}', 'Admin\BibleController@export');
Route::post('/admin/bibles/meta/{id}', 'Admin\BibleController@meta');
Route::post('/admin/bibles/revert/{id}', 'Admin\BibleController@revert');
Route::post('/admin/bibles/test/{id}', 'Admin\BibleController@test');
Route::post('/admin/bibles/update/{id}', 'Admin\BibleController@updateModule');
// Route::post('/admin/bibles/delete/{id}', 'Admin\BibleController@delete');
Route::post('/admin/bibles/delete/{id}', 'Admin\BibleController@destroy');
Route::post('/admin/bibles/unique', 'Admin\BibleController@uniqueCheck');
Route::get('/admin/bibles/unique', 'Admin\BibleController@uniqueCheck');
Route::post('/admin/bibles/importcheck', 'Admin\BibleController@importCheck');
Route::post('/admin/bibles/import', 'Admin\BibleController@import');

Route::get('/admin/languages', 'Admin\LanguageConfigController@index')->name('admin.languages');
Route::post('/admin/languages', 'Admin\LanguageConfigController@index');
Route::get('/admin/languages/fetch/{id}', 'Admin\LanguageConfigController@fetch');
Route::post('/admin/languages/save', 'Admin\LanguageConfigController@save');

Route::get('/admin/tos', 'Admin\PostConfigController@tos')->name('admin.tos')->middleware('install');
Route::post('/admin/tos', 'Admin\PostConfigController@saveTos');
Route::get('/admin/privacy', 'Admin\PostConfigController@privacy')->name('admin.privacy')->middleware('install');
Route::post('/admin/privacy', 'Admin\PostConfigController@savePrivacy');

Route::resource('/admin/bibles', 'Admin\BibleController', ['as' => 'admin']);

// Route::resource('/admin/bibles', 'Admin\BibleController', ['as' => 'admin', 'except' => [
//     'create', 'edit'// , 'update'
// ]]);

Route::get('/admin/config', 'Admin\ConfigController@index')->name('admin.configs')->middleware('install');
Route::post('/admin/config', 'Admin\ConfigController@store')->name('admin.configs.store');
Route::delete('/admin/config', 'Admin\ConfigController@destroy')->name('admin.configs.destroy');
Route::post('/admin/config/download/cleanup', 'Admin\ConfigController@cleanUpDownloadFiles');
Route::post('/admin/config/download/delete', 'Admin\ConfigController@deleteAllDownloadFiles');
// Route::post('/admin/config/download_delete', 'Admin\ConfigController@index');

//Route::controller('admin', 'AdminController');

// Installers
Route::get('/install/{action?}' , 'Admin\InstallController@index')->name('admin.install');
//Route::post('/install/{action?}', 'Admin\InstallController@genericAction'); // Inside controller actions are required to be post
Route::post('/install/check', 'Admin\InstallController@check')->name('admin.install.check'); // Inside controller actions are required to be post
Route::post('/install/config', 'Admin\InstallController@config')->name('admin.install.config'); // Inside controller actions are required to be post
Route::post('/install/config/process', 'Admin\InstallController@handleConfig')->name('admin.install.config.process'); // Inside controller actions are required to be post
Route::get('/install/config/process', 'Admin\InstallController@index');

// todos
Route::get('/admin/options', 'AdminController@todo')->name('admin.options');
Route::get('/admin/test', 'AdminController@todo')->name('admin.test');