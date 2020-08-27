<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Movie;
use App\Cinema;
use App\MoviesInCinema;

use Goutte\Client;

class BackupController extends BaseController
{
    public function backupData(){
        $successMultikino = $this->_multikino();
        
        $successCinemacity = $this->_cinemacity();

        $successKinoMoranow = $this->_kinoMoranow();

        if($successMultikino && $successCinemacity && $successKinoMoranow)
            return $this->sendResponse("", 'Backup completed successfully.');
        else
            return $this->sendError('Backup could not be completed.', 500);
    
    }

    function _multikino(){
        $cinema = Cinema::firstWhere('name', '=', "Multikino");
        // $response = Http::get('https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=12-08-2020&type=PRZEDSPRZEDAŻ');

        $response = Http::get('https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=28-08-2020');
        

        $result = collect();

        foreach ($response["WhatsOnAlphabeticFilms"] as $key => $item) {
            $linkCinemaMoviePage = "https://multikino.pl".$item['FilmUrl'];
            $title = $item['Title'];
            $description = $item['ShortSynopsis'];//<-----
            $duration = 0;
            $classification = ($item['CertificateAge'] !== "") ? $item['CertificateAge']."+" : $item['CertificateAge'];//<-----
            $year = "" ;
            $poster = $item['Poster'];
            $trailer = $item['TrailerUrl'];

            $this->_insertMovie($cinema, $linkCinemaMoviePage, $title, $description, $duration, $classification, $year, $trailer, $poster);

            // $language = $this->_getLanguage($item['WhatsOnAlphabeticCinemas'][0]) 
        }
        return true;
    }

    function _cinemacity(){
        $cinema = Cinema::firstWhere('name', '=', "Cinema City");
        $response = Http::get('https://www.cinema-city.pl/pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/1070/at-date/2020-08-28?attr=&lang=pl_PL');

        $result = collect();

        foreach ($response["body"]["films"] as $key => $item) {
            $linkCinemaMoviePage = $item['link'];
            $title = $item['name'];
            $description = "";
            $duration = ($item['length'] !== "") ? intval($item['length']) : 0;//<-----
            $classification = "";
            $year = $item['releaseYear']; //<-----
            $poster = $item['posterLink'];
            $trailer = $item['videoLink'];

            $this->_insertMovie($cinema, $linkCinemaMoviePage, $title, $description, $duration, $classification, $year, $trailer, $poster);

            // $language = $item['attributeIds']
        }
        return true;
    }

    function _kinoMoranow(){
        $client = new Client();

        $crawler = $client->request('GET', 'https://kinomuranow.pl/repertuar?month=2020-08-28');
        $cinema = Cinema::firstWhere('name', '=', "Kino Muranow");

        $movie = $crawler
            ->filter("div.rep-film-desc-wrapper")
            ->each(function ($node) use ($cinema, $client) {

                $linkCinemaMoviePage = "https://kinomuranow.pl/".$node->filter("div.rep-film-desc-mobile a")->attr('href');
                
                //----- Second crawling
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
                                    preg_match_all('!\d+!', $duration, $matches);//we extract duration from string. Ex.: '110min.' -> 110
                                    $duration = $matches[0][0];
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

                $poster = "";
                if($node->filter("div.rep-film-show-hover-wrapper img")->count() > 0){
                    $poster = $node->filter("div.rep-film-show-hover-wrapper img")->attr('src');
                }

                $description = "";
                $trailer = "";
                foreach ($movieDesc as $key => $item) {
                    $description = $item['description'];
                    $trailer = $item['trailer_url'];
                }

                $duration = "";
                $classification = "";
                $year = "";
                foreach($movieDetails as $key => $item) {
                    $classification = $item['category'];
                    $year = $item['production_date'];
                    $duration = intval($item['duration']);
                }

                $this->_insertMovie($cinema, $linkCinemaMoviePage, $title, $description, $duration, $classification, $year, $trailer, $poster);
        });

        return true;
    }

    function _insertMovie($cinema, $linkCinemaMoviePage, $title, $description, $duration, $classification, $year, $trailer, $poster){
        $movie = Movie::firstWhere('title', '=', $title);

        if(!$movie){ //if the movie does not exist
            
            //first create the new movie
            $newMovie = Movie::create([
                "title" => $title,
                "description" => isset($description) ? $description : "",
                "duration" => $duration,
                "classification" => isset($classification) ? $classification : "",
                "release_year" => isset($year) ? $year : "",
                "trailer_url" => isset($trailer) ? $trailer : "",
                "poster_url" => isset($poster) ? $poster : ""
            ]);

            // //then associate the movie with the cinema
            if(!is_null($newMovie)){
                $movieInserted = Movie::where("title","=", $title)->first();

                $moviesInCinema = MoviesInCinema::create([
                    "movie_id" => $movieInserted->id,
                    "cinema_id" => $cinema->id,
                    "cinema_movie_url" => $linkCinemaMoviePage
                ]);
            }

        } else { //if the movie already exists

            //We update movie values in case the new movie has them
            $updateValues = false;

            if($movie->title === "" && $title !== ""){
                $movie->title = $title;
                $updateValues = true;
            }

            if($movie->description === "" && $description !== ""){
                $movie->description = $description;
                $updateValues = true;
            }

            if($movie->duration === 0 && $duration > 0){
                $movie->duration = $duration;
                $updateValues = true;
            }

            if($movie->classification === "" && $classification !== ""){
                $movie->classification = $classification;
                $updateValues = true;
            }

            if($movie->release_year === "" && $year !== ""){
                $movie->release_year = $year;
                $updateValues = true;
            }

            if($movie->trailer_url === "" && $trailer !== ""){
                $movie->trailer_url = $trailer;
                $updateValues = true;
            }

            if($movie->poster_url === "" && $poster !== ""){
                $movie->poster_url = $poster;
                $updateValues = true;
            }

            if($updateValues)
                $movie->save();

            
            //we find if the cinema is already associated with the movie
            $moviesInCinema = MoviesInCinema::where("movie_id","=", $movie->id)->where("cinema_id","=", $cinema->id)->first();
        
            if(!$moviesInCinema){   //if the cinema if not associated with the movie, we create it
                $moviesInCinema = MoviesInCinema::create([
                    "movie_id" => $movie->id,
                    "cinema_id" => $cinema->id,
                    "cinema_movie_url" => $linkCinemaMoviePage
                ]);
            }
        }
    }

    function _getLanguage($value){
        foreach ($value["WhatsOnAlphabeticCinemas"] as $key => $item) {
            foreach ($item["WhatsOnAlphabeticShedules"] as $key2 => $item2) {
                return $item2['VersionTitle'];
            }
        }
    }

}