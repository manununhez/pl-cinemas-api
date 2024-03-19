<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BackupController;

use App\Cinema;
use App\CinemaLocation;

use DateTime;
use DateInterval;

class AttributeController extends BaseController
{
    public function index()
    {
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

        return $this->sendResponse($result, $message);
    }
}
