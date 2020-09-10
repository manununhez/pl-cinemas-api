<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Movie;
use App\Cinema;
use App\MoviesInCinema;
use App\CinemaLocation;

use DateTime;
use DateInterval;
use Goutte\Client;

//TODO Add backup of movies for the next X days in advance
class BackupController extends BaseController
{
    const MULTIKINO = "Multikino";
    const MULTIKINO_BASE_URL = "https://multikino.pl";

    const CINEMACITY = "Cinema City";
    const CINEMACITY_BASE_URL = "https://www.cinema-city.pl/";

    const KINO_MORANOW = "Kino Muranow";
    const KINO_MORANOW_BASE_URL = "https://kinomuranow.pl/";

    const DAYS_IN_ADVANCE = 10;
    const DAYS_START_FROM_TODAY = 0;
    const DAYS_START_FROM_TOMORROW = 1;

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

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $d = new DateTime();
            $d->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $dateSearch = $d->format('d-m-Y');//date("d-m-Y");//now
            $date = $d->format('Y-m-d');
            $this->getMoviesFromMultikino($cinema, $dateSearch, $date);
        }

        return true;
    }

    function getMoviesFromMultikino($cinema, $dateSearch, $date){
        $responseCinemas = $this->getMultikinoCinemasURL();
        foreach ($responseCinemas["venues"] as $keyC => $itemC) {
            foreach ($itemC["cinemas"] as $keyD => $itemD) {
                //CREATE CINEMA LOCATIONS
                $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', $itemD['id'])
                                ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                                ->first();
                //if locations does not exist, we'll create it
                if(is_null($cinemaLocation)){ 
                    $city = explode(" ", $itemD['search']);
                    //create locations of the cinema
                    $cinemaLocation = CinemaLocation::create([
                        CinemaLocation::LOCATION_ID => $itemD['id'],
                        CinemaLocation::NAME => $itemD['search'],
                        CinemaLocation::CITY => $city[0],
                        CinemaLocation::CINEMA_ID => $cinema->id
                    ]);
                }

                //GET movies from the selected cinema location
                $responseMovies = $this->getMultikinoMoviesURL($cinemaLocation->location_id, $dateSearch);
                foreach ($responseMovies["WhatsOnAlphabeticFilms"] as $key => $item) {

                    $filmParams = collect($item['FilmParams'])->pluck('Title');
                    $durationFromFilmParams = $filmParams->pop();

                    $classificationFromFilmParams = $filmParams->filter(function ($value, $key) {
                                                                    return (strpos($value, "Od lat") === false);
                                                                })->reduce(function ($carry, $item) {
                                                                    return $carry."|".$item;
                                                                }, "");

                    $linkCinemaMoviePage = self::MULTIKINO_BASE_URL.$item['FilmUrl'];
                    
                    $movie = new Movie;
                    $movie->title = $this->isNotNull($item['Title']);
                    $movie->description = $this->isNotNull($item['ShortSynopsis']);//<-----
                    $movie->duration = (strpos($durationFromFilmParams, "minut") !== false) ? explode(" ",$durationFromFilmParams)[0] : 0; //extract movie duration value
                    $movie->classification = $classificationFromFilmParams;//($item['CertificateAge'] !== "") ? $item['CertificateAge']."+" : $item['CertificateAge'];//<-----
                    $movie->release_year = "" ;
                    $movie->poster_url = $this->isNotNull($item['Poster']);
                    $movie->trailer_url = $this->isNotNull($item['TrailerUrl']);

                    $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date);

                    // $language = $this->_getLanguageFromMultikino($item['WhatsOnAlphabeticCinemas'][0]) 
                }
            }   
        }
    }

    function _cinemacity(){

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::CINEMACITY);
        $language = "pl_PL";

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime();
            $date->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $date = $date->format('Y-m-d');//date("d-m-Y");//now
            $this->getMoviesFromCinemaCity($cinema, $date, $language);
        }
        
        
        
        return true;
    }

    function getMoviesFromCinemaCity($cinema, $date, $language){
        $responseCinemas = $this->getCinemaCityCinemasURL($date, $language);
        foreach ($responseCinemas["body"]["cinemas"] as $keyC => $itemC) {
            //CREATE CINEMA LOCATIONS
            $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', $itemC['id'])
                            ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                            ->first();
            //if locations does not exist, we'll create it
            if(is_null($cinemaLocation)){ 
                //create locations of the cinema
                $cinemaLocation = CinemaLocation::create([
                    CinemaLocation::LOCATION_ID => $itemC['id'],
                    CinemaLocation::NAME => $itemC['displayName'],
                    CinemaLocation::CITY => $itemC['addressInfo']['city'],
                    CinemaLocation::CINEMA_ID => $cinema->id,
                    CinemaLocation::COORD_LATITUDE => $itemC['latitude'],
                    CinemaLocation::COORD_LONGITUDE => $itemC['longitude']
                ]);
            }

            $websiteCinema = $itemC['link'];
            $cinemaID = $itemC['id'];

            //GET movies from the selected cinema location
            $responseMovies = $this->getCinemaCityMoviesURL($cinemaLocation->location_id, $date, $language);
            foreach ($responseMovies["body"]["films"] as $key => $item) {
                // plus / na / bez-ograniczen --->Age restriction
                // dubbed, subbed, original-lang,first-subbed-lang ---> languages 
                $classification = "";
                foreach ($item['attributeIds'] as &$attr) {
                    if((strpos($attr, "lang") === false) && (strpos($attr, "sub") === false) && (strpos($attr, "dub") === false) &&  (strpos($attr, "2d") === false) && (strpos($attr, "3d") === false)
                    && (strpos($attr, "na") === false) && (strpos($attr, "plus") === false) && (strpos($attr, "ograniczen") === false)){
                        $classification = $classification."|".$attr; //we saved only attributes refering to movie categories
                    }
                }


                // $linkCinemaMoviePage = $item['link'];
                $linkCinemaMoviePage = $websiteCinema."/".$cinemaID."#/buy-tickets-by-cinema?in-cinema=".$cinemaLocation->id."&at=".$date."&for-movie=".$item["id"]."&view-mode=list";

                $movie = new Movie;
                $movie->title = $this->isNotNull($item['name']);
                $movie->description = "";
                $movie->duration = ($item['length'] !== "") ? intval($item['length']) : 0;//<-----
                $movie->classification = $classification;
                $movie->release_year = $this->isNotNull($item['releaseYear']); //<-----
                $movie->poster_url = $this->isNotNull($item['posterLink']);
                $movie->trailer_url = $this->isNotNull($item['videoLink']);

                $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date);

                // $language = $item['attributeIds']
            }
        }
    }

    function _kinoMoranow(){

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINO_MORANOW);

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime();
            $date->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $date = $date->format('Y-m-d');//date("d-m-Y");//now
            $this->getMoviesFromKinoMoranow($cinema, $date);
        }

        return true;
    }

    function getMoviesFromKinoMoranow($cinema, $date){
        $client = new Client();

        $crawler = $client->request('GET', $this->getKinoMoranowMoviesURL($date));

        $cinemaLocationKinoMuranow = 156;
        $cinemaLocationName = "Warszawa";
        $cityName = "Warszawa";
        //CREATE CINEMA LOCATIONS
        $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', $cinemaLocationKinoMuranow)
                                        ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                                        ->first();
        //if locations does not exist, we'll create it
        if(is_null($cinemaLocation)){ 
            //create locations of the cinema
            $cinemaLocation = CinemaLocation::create([
                CinemaLocation::LOCATION_ID => $cinemaLocationKinoMuranow,
                CinemaLocation::NAME => $cinemaLocationName,
                CinemaLocation::CINEMA_ID => $cinema->id,
                CinemaLocation::CITY => $cityName,

            ]);
        }

        $movie = $crawler
            ->filter("div.rep-film-desc-wrapper")
            ->each(function ($node) use ($cinemaLocation, $cinema, $client, $date) {
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

                // $this->_insertMovie($cinema, $linkCinemaMoviePage, $movie);
                $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date);
        });
    }

    function _insertMovie($cinemaId, $locationId, $linkCinemaMoviePage, Movie $movieToInsert, $date){
        $movie = Movie::firstWhere(Movie::TITLE, '=', $movieToInsert->title);

        if(!$movie){ //if the movie does not exist           
            //first create the new movie and get the inserted ID
            //then associate the movie with the cinema
            if($movieToInsert->save()){               
                $moviesInCinema = MoviesInCinema::create([
                    MoviesInCinema::MOVIE_ID => $movieToInsert->id,
                    MoviesInCinema::CINEMA_ID => $cinemaId,
                    MoviesInCinema::LOCATION_ID => $locationId,
                    MoviesInCinema::DAY_TITLE => $date,
                    MoviesInCinema::CINEMA_MOVIE_URL => $linkCinemaMoviePage
                ]);
                // echo($moviesInCinema);
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
                                            ->where(MoviesInCinema::CINEMA_ID,"=", $cinemaId)
                                            ->where(MoviesInCinema::LOCATION_ID,"=", $locationId)
                                            ->where(MoviesInCinema::DAY_TITLE,"=", $date)
                                            ->first();
        
            if(!$moviesInCinema){   //if the cinema if not associated with the movie, we create it
                $moviesInCinema = MoviesInCinema::create([
                    MoviesInCinema::MOVIE_ID => $movie->id,
                    MoviesInCinema::CINEMA_ID => $cinemaId,
                    MoviesInCinema::LOCATION_ID => $locationId,
                    MoviesInCinema::DAY_TITLE => $date,
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

    function getMultikinoMoviesURL($cinemaId, $date){
        // 'https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=12-08-2020&type=PRZEDSPRZEDAŻ'
        return Http::get("https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=".$cinemaId."&data=".$date);
    }

    function getMultikinoCinemasURL(){
        return Http::get("https://multikino.pl/data/locations/");
    }

    function getCinemaCityMoviesURL($cinemaId, $date, $language){
        return Http::get("https://www.cinema-city.pl/pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/".$cinemaId."/at-date/".$date."?attr=&lang=".$language);
    }

    function getCinemaCityCinemasURL($date, $language){
        return Http::get("https://www.cinema-city.pl/pl/data-api-service/v1/quickbook/10103/cinemas/with-event/until/".$date."?attr=&lang=".$language);
    }

    function getKinoMoranowMoviesURL($date){
        return 'https://kinomuranow.pl/repertuar?month='.$date;
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