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
    const MULTIKINO = "Multikino";
    const MULTIKINO_BASE_URL = "https://multikino.pl";

    const CINEMACITY = "Cinema City";
    const CINEMACITY_BASE_URL = "https://www.cinema-city.pl/";

    const KINO_MORANOW = "Kino Muranow";
    const KINO_MORANOW_BASE_URL = "https://kinomuranow.pl/";

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
    
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::MULTIKINO);

        $response = Http::get($this->getMultikinoURL());
        
        foreach ($response["WhatsOnAlphabeticFilms"] as $key => $item) {
            $linkCinemaMoviePage = self::MULTIKINO_BASE_URL.$item['FilmUrl'];
            
            $movie = new Movie;
            $movie->title = $this->isNotNull($item['Title']);
            $movie->description = $this->isNotNull($item['ShortSynopsis']);//<-----
            $movie->duration = 0;
            $movie->classification = ($item['CertificateAge'] !== "") ? $item['CertificateAge']."+" : $item['CertificateAge'];//<-----
            $movie->release_year = "" ;
            $movie->poster_url = $this->isNotNull($item['Poster']);
            $movie->trailer_url = $this->isNotNull($item['TrailerUrl']);

            $this->_insertMovie($cinema, $linkCinemaMoviePage, $movie);

            // $language = $this->_getLanguageFromMultikino($item['WhatsOnAlphabeticCinemas'][0]) 
        }
        return true;
    }

    function _cinemacity(){

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::CINEMACITY);
        $response = Http::get($this->getCinemaCityURL());

        foreach ($response["body"]["films"] as $key => $item) {
            $linkCinemaMoviePage = $item['link'];

            $movie = new Movie;
            $movie->title = $this->isNotNull($item['name']);
            $movie->description = "";
            $movie->duration = ($item['length'] !== "") ? intval($item['length']) : 0;//<-----
            $movie->classification = "";
            $movie->release_year = $this->isNotNull($item['releaseYear']); //<-----
            $movie->poster_url = $this->isNotNull($item['posterLink']);
            $movie->trailer_url = $this->isNotNull($item['videoLink']);

            $this->_insertMovie($cinema, $linkCinemaMoviePage, $movie);

            // $language = $item['attributeIds']
        }
        return true;
    }

    function _kinoMoranow(){

        $client = new Client();

        $crawler = $client->request('GET', $this->getKinoMoranow());
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINO_MORANOW);

        $movie = $crawler
            ->filter("div.rep-film-desc-wrapper")
            ->each(function ($node) use ($cinema, $client) {
                $linkCinemaMoviePage = self::KINO_MORANOW_BASE_URL.$node->filter("div.rep-film-desc-mobile a")->attr('href');
                
                //----- Second crawling ------
                $secondCrawler = $client->request('GET', $linkCinemaMoviePage);
                $movieDesc = $secondCrawler
                            ->filter("div.region-content div.content-movie")
                            ->each(function ($node2) {
                                $description = $this->isNodeIsNotEmptyText($node2->filter("div.content-movie-body p"));
                                $trailer = $this->isNodeIsNotEmptyAttr($node2->filter("div.content-movie-simple-video div.youtube-container--responsive iframe"), 'src');

                                $result = [
                                    "description" => $description,
                                    "trailer_url" => $trailer
                                ];
                                return $result;
                            });
                $movieDetails = $secondCrawler
                            ->filter("div.view-movies div.movie-info-box-row")
                            ->each(function ($node2) {
                                $titlePL = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-polish-title div.field-content"));
                                $language = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-language div.field-content"));
                                $category = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-category div.field-content"));
                                $titleOriginal = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-original-title div.field-content"));
                                $direction = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-direction div.field-content"));
                                $productionDate = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-production-date div.field-content"));
                                $productionCountry = $this->isNodeIsNotEmptyText($node2->filter("div.views-field-field-movie-production-country div.field-content"));

                                $durationNode = $node2->filter("div.views-field-field-movie-duration div.field-content");
                                $duration = 0; 
                                if($durationNode->count() > 0){
                                    preg_match_all('!\d+!', $durationNode->text(), $matches);//we extract duration from string. Ex.: '110min.' -> 110
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
                //----- end second crawling ------                 
                
                $movie = new Movie;

                $movie->title = $this->isNodeIsNotEmptyText($node->filter("div.rep-film-desc-mobile div.rep-film-show-title"));

                $movie->poster_url = $this->isNodeIsNotEmptyAttr($node->filter("div.rep-film-show-hover-wrapper img"), 'src');

                foreach ($movieDesc as $key => $item) {
                    $movie->description = $this->isNotNull($item['description']);
                    $movie->trailer_url = $this->isNotNull($item['trailer_url']);
                }

                foreach($movieDetails as $key => $item) {
                    $movie->classification = $this->isNotNull($item['category']);
                    $movie->release_year = $this->isNotNull($item['production_date']);
                    $movie->duration = isset($item['duration']) ? intval($item['duration']) : 0;
                }

                $this->_insertMovie($cinema, $linkCinemaMoviePage, $movie);
        });

        return true;
    }

    function _insertMovie($cinema, $linkCinemaMoviePage, Movie $movieToInsert){
        $movie = Movie::firstWhere(Movie::TITLE, '=', $movieToInsert->title);

        if(!$movie){ //if the movie does not exist           
            //first create the new movie and get the inserted ID
            //then associate the movie with the cinema
            if($movieToInsert->save()){
                $movieInserted = Movie::where(Movie::TITLE,"=", $movieToInsert->title)
                                ->first();
                
                $moviesInCinema = MoviesInCinema::create([
                    MoviesInCinema::MOVIE_ID => $movieInserted->id,
                    MoviesInCinema::CINEMA_ID => $cinema->id,
                    MoviesInCinema::CINEMA_MOVIE_URL => $linkCinemaMoviePage
                ]);
            }
        } else { //if the movie already exists

            //We update movie values in case the new movie has them
            $updateValues = false;

            if($movie->title === "" && $movieToInsert->title !== ""){
                $movie->title = $movieToInsert->title;
                $updateValues = true;
            }

            if($movie->description === "" && $movieToInsert->description !== ""){
                $movie->description = $movieToInsert->description;
                $updateValues = true;
            }

            if($movie->duration === 0 && $movieToInsert->duration > 0){
                $movie->duration = $movieToInsert->duration;
                $updateValues = true;
            }

            if($movie->classification === "" && $movieToInsert->classification !== ""){
                $movie->classification = $movieToInsert->classification;
                $updateValues = true;
            }

            if($movie->release_year === "" && $movieToInsert->release_year !== ""){
                $movie->release_year = $movieToInsert->release_year;
                $updateValues = true;
            }

            if($movie->trailer_url === "" && $movieToInsert->trailer_url !== ""){
                $movie->trailer_url = $movieToInsert->trailer_url;
                $updateValues = true;
            }

            if($movie->poster_url === "" && $movieToInsert->poster_url !== ""){
                $movie->poster_url = $movieToInsert->poster_url;
                $updateValues = true;
            }

            if($updateValues)
                $movie->save();

            
            //we find if the cinema is already associated with the movie
            $moviesInCinema = MoviesInCinema::where(MoviesInCinema::MOVIE_ID,"=", $movie->id)
                                            ->where(MoviesInCinema::CINEMA_ID,"=", $cinema->id)
                                            ->first();
        
            if(!$moviesInCinema){   //if the cinema if not associated with the movie, we create it
                $moviesInCinema = MoviesInCinema::create([
                    MoviesInCinema::MOVIE_ID => $movie->id,
                    MoviesInCinema::CINEMA_ID => $cinema->id,
                    MoviesInCinema::CINEMA_MOVIE_URL => $linkCinemaMoviePage
                ]);
            }
        }
    }

    function _getLanguageFromMultikino($value){
        foreach ($value["WhatsOnAlphabeticCinemas"] as $key => $item) {
            foreach ($item["WhatsOnAlphabeticShedules"] as $key2 => $item2) {
                return $item2['VersionTitle'];
            }
        }
    }

    function getMultikinoURL(){
        return "https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=".date("d-m-Y");
    }

    function getCinemaCityURL(){
        return 'https://www.cinema-city.pl/pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/1070/at-date/'.date("Y-m-d").'?attr=&lang=pl_PL';
    }

    function getKinoMoranow(){
        // 'https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=12-08-2020&type=PRZEDSPRZEDAŻ'
        return 'https://kinomuranow.pl/repertuar?month='.date("Y-m-d");
    }

    function isNotNull($item){
        return isset($item) ? $item : "";
    }
    
    function isNodeIsNotEmptyText($node){
        return ($node->count() > 0) ? $node->text() : "";
    }

    function isNodeIsNotEmptyAttr($node, $attribute){
        return ($node->count() > 0) ? $node->attr($attribute) : "";
    }

}