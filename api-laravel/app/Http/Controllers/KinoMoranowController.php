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
            ->each(function ($node) use ($client) {
                $linkCinemaMoviePage = "https://kinomuranow.pl/".$node->filter("div.rep-film-desc-mobile a")->attr('href');

                //----- Second crawling
                // $client = new Client();
                $secondCrawler = $client->request('GET', $linkCinemaMoviePage);
                $movieDesc = $secondCrawler
                            ->filter("div.region-content div.content-movie")
                            ->each(function ($node2) {
                                $description = "";
                                if($node2->filter("div.content-movie-body p")->count() > 0){
                                    $description = $node2->filter("div.content-movie-body p")->text();
                                }

                                $trailer = "";
                                if($node2->filter("div.content-movie-simple-video div.youtube-container--responsive iframe")->count() > 0){
                                    $trailer = $node2->filter("div.content-movie-simple-video div.youtube-container--responsive iframe")->attr('src');
                                }


                                $result = [
                                    "description" => $description,
                                    "trailer_url" => $trailer
                                ];
                                return $result;
                            });
                $movieDetails = $secondCrawler
                            ->filter("div.view-movies div.movie-info-box-row")
                            ->each(function ($node2) {
                                $titlePL = "";
                                if($node2->filter("div.views-field-field-movie-polish-title div.field-content")->count() > 0){
                                    $titlePL = $node2->filter("div.views-field-field-movie-polish-title div.field-content")->text();
                                }

                                $language = "";
                                if($node2->filter("div.views-field-field-movie-language div.field-content")->count() > 0){
                                    $language = $node2->filter("div.views-field-field-movie-language div.field-content")->text();
                                }

                                $category = "";
                                if($node2->filter("div.views-field-field-movie-category div.field-content")->count() > 0){
                                    $category = $node2->filter("div.views-field-field-movie-category div.field-content")->text();
                                }

                                $titleOriginal = "";
                                if($node2->filter("div.views-field-field-movie-original-title div.field-content")->count() > 0){
                                    $titleOriginal = $node2->filter("div.views-field-field-movie-original-title div.field-content")->text();
                                }

                                $direction = "";
                                if($node2->filter("div.views-field-field-movie-direction div.field-content")->count() > 0){
                                    $direction = $node2->filter("div.views-field-field-movie-direction div.field-content")->text();
                                }

                                $productionDate = "";
                                if($node2->filter("div.views-field-field-movie-production-date div.field-content")->count() > 0){
                                    $productionDate = $node2->filter("div.views-field-field-movie-production-date div.field-content")->text();
                                }

                                $productionCountry = "";
                                if($node2->filter("div.views-field-field-movie-production-country div.field-content")->count() > 0){
                                    $productionCountry = $node2->filter("div.views-field-field-movie-production-country div.field-content")->text();
                                }

                                $duration = "";
                                if($node2->filter("div.views-field-field-movie-duration div.field-content")->count() > 0){
                                    $duration = $node2->filter("div.views-field-field-movie-duration div.field-content")->text();
                                }

                                $result = [
                                    "title_pl" => $titlePL,
                                    "title_original" => $titleOriginal,
                                    "category" => $category,
                                    "language" => $language,
                                    "direction" => $direction,
                                    "production_date" => $productionDate,
                                    "production_country" => $productionCountry,
                                    "duration" => $duration,
                                ];
                                return $result;
                            });
                //----- end second crawling
                $title = "";
                if($node->filter("div.rep-film-desc-mobile div.rep-film-show-title")->count() > 0){
                    $title = $node->filter("div.rep-film-desc-mobile div.rep-film-show-title")->text();
                }

                $showDate = "";
                if($node->filter("div.rep-film-desc-mobile div.rep-film-show-date")->count() > 0){
                    $showDate = $node->filter("div.rep-film-desc-mobile div.rep-film-show-date")->text();
                }

                $image_url = "";
                if($node->filter("div.rep-film-show-hover-wrapper img")->count() > 0){
                    $image_url = $node->filter("div.rep-film-show-hover-wrapper img")->attr('src');
                }

                $description = "";
                $trailer = "";
                foreach ($movieDesc as $key => $item) {
                    $description = $item['description'];
                    $trailer = $item['trailer_url'];
                }

                foreach($movieDetails as $key => $item) {
                    $titlePL = $item['title_pl'];
                    $titleOriginal = $item['title_original'];
                    $category = $item['category'];
                    $language = $item['language'];
                    $direction = $item['direction'];
                    $productionDate = $item['production_date'];
                    $productionCountry = $item['production_country'];
                    $duration = $item['duration'];
                }

                $result = [
                    "title" => $title,
                    "description_url" =>  $linkCinemaMoviePage,
                    "description" => $description,
                    "trailer" => $trailer,
                    "title_pl" => $titlePL,
                    "title_original" => $titleOriginal,
                    "category" => $category,
                    "language" => $language,
                    "direction" => $direction,
                    "production_date" => $productionDate,
                    "production_country" => $productionCountry,
                    "duration" => $duration,
                    "show_date" => $showDate,
                    "image_url" => $image_url
                ];
                
                return $result;
        });

        return $this->sendResponse($movie, "");
    }
}
