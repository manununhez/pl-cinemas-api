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
Route::get('dates', function (Request $request) {
    $d = collect();
    for ($x = BackupController::DAYS_START_FROM_TODAY; $x <= BackupController::DAYS_IN_ADVANCE; $x++) {
        $date = new DateTime(BackupController::TIMEZONE);
        $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
        $date = $date->format('Y-m-d'); //date("d-m-Y");//now

        $d->push(["date" => $date]);
    }
    $result = [
        "success" => true,
        "data" => $d,
        "message" => BackupController::DAYS_IN_ADVANCE . " dates in advance."
    ];
    return $result;
});

Route::get('movies-1', 'CinemaCityController@index');
Route::get('movies-2', 'MultikinoController@index');
Route::get('movies-3', 'KinoMoranowController@index');

Route::get('locations', function (Request $request) {
    $city = new CinemaLocation();
    $result = [
        "success" => true,
        "data" => $city->getCinemaCities(),
        "message" => "Cities successfully delivered."
    ];
    return $result;
});

Route::get('attributes', function (Request $request) {
    $d = collect();
    for ($x = BackupController::DAYS_START_FROM_TODAY; $x <= BackupController::DAYS_IN_ADVANCE; $x++) {
        $date = new DateTime(BackupController::TIMEZONE);
        $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
        $date = $date->format('Y-m-d'); //date("d-m-Y");//now

        $d->push($date);
    }

    $city = new CinemaLocation();
    $cinema = new Cinema();
    $result = [
        "cinemas" => $cinema->getCinemas(),
        "cities" => $city->getCinemaCities(),
        "days" => $d,
        "languages" => BackupController::LANGUAGES
    ];
    $message = "Cities successfully delivered.";

    $response = [
        'success' => true,
        'data'    => [
            'result' => $result,
            'timestamp' => now()->toDateTimeString(), // Add timestamp to the data
        ],
        'message' => $message,
    ];
    return $response;
});

Route::post('movies/search', 'MovieController@getMoviesByLocation');
// Route::get('movies', 'MovieController@index');
Route::post('movies', 'MovieController@store');
Route::delete('movies/{id}', 'MovieController@delete');

Route::get('cinemas', 'CinemaController@index');
Route::post('cinemas', 'CinemaController@store');
Route::delete('cinemas/{id}', 'CinemaController@delete');
