<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Cinema;
use App\CinemaLocation;

use App\Http\Controllers\BackupController;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('backup', 'BackupController@backupData');
Route::get('backup2', 'BackupTmpController@backupData');

Route::get('movies-1', 'CinemaCityController@index');
Route::get('movies-2', 'MultikinoController@index');
Route::get('movies-3', 'KinoMoranowController@index');

Route::post('attributes', 'MovieController@getAttributes');
Route::post('movies/search', 'MovieController@searchMoviesByFilterAttr');
Route::post('movies', 'MovieController@store');
Route::delete('movies/{id}', 'MovieController@delete');

Route::get('cinemas', 'CinemaController@index');
Route::post('cinemas', 'CinemaController@store');
Route::delete('cinemas/{id}', 'CinemaController@delete');
