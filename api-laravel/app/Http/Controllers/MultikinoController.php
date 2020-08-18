<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MultikinoController extends BaseController
{
    public function index()
    {
        // $response = Http::get('https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=12-08-2020&type=PRZEDSPRZEDAŻ');

        $response = Http::get('https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43');
        

        $result = collect();

        foreach ($response["WhatsOnAlphabeticFilms"] as $key => $item) {
            $result->push([ 
                "title" => $item['Title'],
                "short_description" => $item['ShortSynopsis'],
                "description" => $item['Synopsis'],
                "description_url" => $item['FilmUrl'],
                "trailer_url" => $item['TrailerUrl'],
                "image_url" => $item['Poster'],
                "language" => $this->getLanguage($item['WhatsOnAlphabeticCinemas'][0])
            ]);   
        }
        return $this->sendResponse($result, '');
    }

    function getLanguage($value){
        foreach ($value["WhatsOnAlphabeticCinemas"] as $key => $item) {
            foreach ($item["WhatsOnAlphabeticShedules"] as $key2 => $item2) {
                return $item2['VersionTitle'];
            }
        }
    }
}