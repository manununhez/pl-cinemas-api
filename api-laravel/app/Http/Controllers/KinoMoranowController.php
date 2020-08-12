<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use Goutte\Client;
 
class KinoMoranowController extends BaseController
{
    public function index()
    {
        $client = new Client();

        $crawler = $client->request('GET', 'https://kinomuranow.pl/repertuar?month=2020-08');

        $movie = $crawler
            ->filter("div.rep-film-desc-wrapper")
            // ->reduce(function ($node) use ($temp) {
            //     return !in_array($node->filter("div.rep-film-desc-mobile a")->attr('href'), $temp);
            // })
            ->each(function ($node) {
                $result = [
                    "title" => $node->filter("div.rep-film-desc-mobile div.rep-film-show-title")->text(),
                    "description_url" =>  $node->filter("div.rep-film-desc-mobile a")->attr('href'),
                    "date" => $node->filter("div.rep-film-desc-mobile div.rep-film-show-date")->text(),
                    "image_url" => $node->filter("div.rep-film-show-hover-wrapper img")->attr('src')
                ];

                // array_push($temp, $node->filter("div.rep-film-desc-mobile a")->attr('href'));

                
                return $result;
        });

        return $this->sendResponse($movie, "");
    }
}
