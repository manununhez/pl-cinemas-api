<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use App\Movie;
use App\MoviesInCinema;
use App\CinemaLocation;

class BackupController extends BaseController
{
    const HTTP_CLIENT_TIMEOUT = 60;
    const DAYS_IN_ADVANCE = 7;
    const TIMEZONE = "Europe/Warsaw";
    const DAYS_START_FROM_TODAY = 0;
    const DAYS_START_FROM_TOMORROW = 1;
    const DATE_MOVIE_SEARCH_FORMAT = 'Y-m-d';

    const LANGUAGES_TO_PL = [
        "english" => "angielski",
        "polish" => "polski",
        "french" => "francuski",
        "czech" => "czeski"
    ];

    const LANGUAGES = [
        "angielski",
        "arabski",
        "czeski",
        "francuski",
        "hiszpański",
        "islandzki",
        "japoński",
        "niemiecki",
        "polskie",
        "polski dubbing",
        "polski lektor",
        "portugalski",
        "rosyjski",
        "sycylijski",
        "ukraiński",
        "włoski"
    ];


    const EMPTY_TEXT = "";

    public function backupData()
    {
        //clean previous saved data
        DB::table(Movie::TABLE_NAME)->delete();
        DB::table(MoviesInCinema::TABLE_NAME)->delete();
        DB::table(CinemaLocation::TABLE_NAME)->delete();

        //call and save new data
        $successCinemacity = $this->backupCinemacity();
        $successKinoMoranow = $this->backupKinoMoranow();

        // Check if backups were successful
        if ($successKinoMoranow && $successCinemacity) {
            // Make request to second API endpoint
            $response = Http::get('https://api.kinema.today/collect-data');

            // Check if the API call was successful
            if ($response->successful()) {
                // Return success response
                return $this->sendResponse(self::EMPTY_TEXT, 'Backup completed successfully.');
            } else {
                // Return error response based on API response
                return $this->sendError('Backup could not be completed. Failed to collect data from API.', 500);
            }
        } else {
            // Return error response if backups were not successful
            return $this->sendError('Backup could not be completed.', 500);
        }
    }


    private function backupMultikino()
    {
        $multikinoBackup = new MultikinoBackupController();
        return $multikinoBackup->backup();
    }

    private function backupCinemacity()
    {
        $cinemaCityBackup = new CinemaBackupController();
        return $cinemaCityBackup->backup();
    }

    private function backupKinoMoranow()
    {
        $kinoMoranowBackup = new KMBackupController();
        return $kinoMoranowBackup->backup();
    }
}
