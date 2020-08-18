<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class CinemaCityController extends BaseController
{
    public function index()
    {
        $response = Http::get('https://www.cinema-city.pl/pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/1070/at-date/2020-08-12?attr=&lang=pl_PL');

        $result = collect();

        foreach ($response["body"]["films"] as $key => $item) {
            $result->push([ 
                "title" => $item['name'],
                "description_url" => $item['link'],
                "trailer_url" => $item['videoLink'],
                "image_url" => $item['posterLink'],
                "language" => $item['attributeIds'] 
            ]);   
        }
        return $this->sendResponse($result, '');
    }
}
