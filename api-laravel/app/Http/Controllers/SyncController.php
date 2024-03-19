<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

use App\MoviesInCinema;

class SyncController extends BaseController
{
    public function index()
    {
        $message = "Last sync time successfully delivered.";

        // Original timestamp
        $originalTimestamp = MoviesInCinema::first()['created_at'];

        // Parse the timestamp using Carbon
        $carbonTimestamp = Carbon::parse($originalTimestamp);

        // Format the timestamp as "YYYY-MM-DD HH:MM:SS"
        $formattedTimestamp = $carbonTimestamp->format('Y-m-d H:i:s');

        return $this->sendResponse($formattedTimestamp, $message);
    }
}
