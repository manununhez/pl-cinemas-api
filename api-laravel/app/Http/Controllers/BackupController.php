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
    //TODO retrieve cinemas URL from DB, not from const!
    const MULTIKINO = "Multikino";
    const MULTIKINO_BASE_URL = "https://multikino.pl";

    const CINEMACITY = "Cinema City";
    const CINEMACITY_BASE_URL = "https://www.cinema-city.pl/";

    const KINO_MORANOW = "Kino Muranow";
    const KINO_MORANOW_BASE_URL = "https://kinomuranow.pl/";

    const KINOTEKA = "Kinoteka";
    const KINOTEKA_BASE_URL = "https://kinoteka.pl";

    const DAYS_IN_ADVANCE = 5;
    const TIMEZONE = "Europe/Warsaw";
    const DAYS_START_FROM_TODAY = 0;
    const DAYS_START_FROM_TOMORROW = 1;
    const DATE_MOVIE_SEARCH_FORMAT = 'Y-m-d';

    const LANGUAGES_TO_PL = [
        "english" => "angielski",
        "polish" => "polski",
        "french" => "francuski"
    ];
    
    const POLISH_LANGUAGE_CODE = "pl_PL";

    const KINO_MURANOW_DATA = [
        "id" => 156,
        "city" => "Warszawa",
        "location" => "Warszawa"
    ];

    const KINOTEKA_DATA = [
        "id" => 35,
        "city" => "Warszawa",
        "location" => "Warszawa"
    ];

    const EMPTY_TEXT = "";

    public function backupData(){
        $successMultikino = $this->_multikino();
        
        $successCinemacity = $this->_cinemacity();

        $successKinoMoranow = $this->_kinoMoranow();

        $successKinoteka = $this->_kinoteka();

        // if($successKinoteka)
        if($successMultikino && $successCinemacity && $successKinoMoranow && $successKinoteka && $successKinoteka)
            return $this->sendResponse(self::EMPTY_TEXT, 'Backup completed successfully.');
        else
            return $this->sendError('Backup could not be completed.', 500);
    
    }

    function _multikino(){
    
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::MULTIKINO);

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $d = new DateTime(self::TIMEZONE);
            $d->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $dateSearch = $d->format('d-m-Y');//date("d-m-Y");//now
            $date = $d->format(self::DATE_MOVIE_SEARCH_FORMAT);
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

                    $genreFromFilmParams = $filmParams->filter(function ($value, $key) {
                                                        return (strpos($value, "Od lat") === false);
                                                    })->reduce(function ($carry, $item) {
                                                        return $carry."|".$item;
                                                    }, self::EMPTY_TEXT);

                    $linkCinemaMoviePage = self::MULTIKINO_BASE_URL.$item['FilmUrl'];

                    $movie = new Movie;
                    $movie->title = $this->isNotNull($item['Title']);
                    $movie->description = $this->isNotNull($item['ShortSynopsis']);//<-----
                    $movie->duration = (strpos($durationFromFilmParams, "minut") !== false) ? explode(" ",$durationFromFilmParams)[0] : 0; //extract movie duration value
                    $movie->original_lang = self::EMPTY_TEXT;
                    $movie->genre = $genreFromFilmParams;
                    $movie->classification = ($item['CertificateAge'] !== self::EMPTY_TEXT) ? $item['CertificateAge']."+" : $item['CertificateAge'];//<-----
                    $movie->release_year = self::EMPTY_TEXT ;
                    $movie->poster_url = $this->isNotNull($item['Poster']);
                    $movie->trailer_url = $this->isNotNull($item['TrailerUrl']);

                    $movie_cinema_language = implode("|",$this->_getLanguageFromMultikino($item['WhatsOnAlphabeticCinemas'][0]));

                    $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
                }
            }   
        }
    }

    function _cinemacity(){

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::CINEMACITY);
        $language = self::POLISH_LANGUAGE_CODE;

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(self::TIMEZONE);
            $date->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $date = $date->format(self::DATE_MOVIE_SEARCH_FORMAT);//date("d-m-Y");//now
            $this->getMoviesFromCinemaCity($cinema, $date, self::POLISH_LANGUAGE_CODE);
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
                $genre = self::EMPTY_TEXT;
                $original_language = self::EMPTY_TEXT;
                $movie_cinema_language = self::EMPTY_TEXT;
                $classification = self::EMPTY_TEXT;
                foreach ($item['attributeIds'] as &$attr) {
                    if((strpos($attr, "lang") === false) && 
                        (strpos($attr, "sub") === false) && 
                        (strpos($attr, "dub") === false) &&  
                        (strpos($attr, "2d") === false) && 
                        (strpos($attr, "3d") === false) && 
                        (strpos($attr, "na") === false) && 
                        (strpos($attr, "plus") === false) && 
                        (strpos($attr, "ograniczen") === false)){
                        $genre = $genre."|".$attr; //we saved only attributes refering to movie categories
                    }

                    if((strpos($attr, "original-lang") !== false) || 
                        (strpos($attr, "dubbed-lang") !== false) || 
                        (strpos($attr, "subbed-lang") !== false)){
                        if((strpos($attr, "original-lang") !== false)){
                            if((strpos($attr, "original-lang-en") !== false)){
                                $original_language = self::LANGUAGES_TO_PL['english'];
                            } else if((strpos($attr, "original-lang-pl") !== false)){ 
                                $original_language = self::LANGUAGES_TO_PL['polish'];
                            } else if((strpos($attr, "original-lang-fr-fr") !== false)){ 
                                $original_language = self::LANGUAGES_TO_PL['french'];
                                
                            }else { //other languages like niemiecki 
                                $original_language = $attr;
                            }
                        }

                        $movie_cinema_language = $movie_cinema_language."|".$attr;
                    }

                    if((strpos($attr, "na") !== false) && 
                        (strpos($attr, "plus") !== false) && 
                        (strpos($attr, "ograniczen") !== false)){
                        $classification = $attr;
                    }
                }


                // $linkCinemaMoviePage = $item['link'];
                $linkCinemaMoviePage = $websiteCinema."/".$cinemaID."#/buy-tickets-by-cinema?in-cinema=".$cinemaLocation->id."&at=".$date."&for-movie=".$item["id"]."&view-mode=list";

                $movie = new Movie;
                $movie->title = $this->isNotNull($item['name']);
                $movie->description = self::EMPTY_TEXT;
                $movie->duration = ($item['length'] !== self::EMPTY_TEXT) ? intval($item['length']) : 0;
                $movie->original_lang = $original_language;
                $movie->genre = $genre;
                $movie->classification = $classification;
                $movie->release_year = $this->isNotNull($item['releaseYear']);
                $movie->poster_url = $this->isNotNull($item['posterLink']);
                $movie->trailer_url = $this->isNotNull($item['videoLink']);

                $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
            }
        }
    }

    function _kinoMoranow(){

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINO_MORANOW);

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(self::TIMEZONE);
            $date->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $date = $date->format(self::DATE_MOVIE_SEARCH_FORMAT);//date("d-m-Y");//now
            $this->getMoviesFromKinoMoranow($cinema, $date);
        }

        return true;
    }

    function getMoviesFromKinoMoranow($cinema, $date){
        $client = new Client();

        $crawler = $client->request('GET', $this->getKinoMoranowMoviesURL($date));

        //CREATE CINEMA LOCATIONS
        $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', self::KINO_MURANOW_DATA['id'])
                                        ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                                        ->first();
        //if locations does not exist, we'll create it
        if(is_null($cinemaLocation)){ 
            //create locations of the cinema
            $cinemaLocation = CinemaLocation::create([
                CinemaLocation::LOCATION_ID => self::KINO_MURANOW_DATA['id'],
                CinemaLocation::NAME => self::KINO_MURANOW_DATA['location'],
                CinemaLocation::CINEMA_ID => $cinema->id,
                CinemaLocation::CITY => self::KINO_MURANOW_DATA['city'],
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

                $movie->poster_url = str_replace("cycle_2x1_hover","cycle_1x1", $movie->poster_url); //adjust poster size

                foreach ($movieDesc as $key => $item) {
                    $movie->description = $this->isNotNull($item['description']);
                    $movie->trailer_url = $this->isNotNull($item['trailer_url']);
                }

                foreach($movieDetails as $key => $item) {
                    $movie->original_lang = substr($this->isNotNull($item['language']), 0, strpos($this->isNotNull($item['language']),'('));  //E.g., extract original lang before parenthesis: hiszpański (napisy polskie i angielskie)
                    $movie->genre = $this->isNotNull($item['category']);
                    $movie->classification = self::EMPTY_TEXT;
                    $movie->release_year = $this->isNotNull($item['production_date']);
                    $movie->duration = isset($item['duration']) ? intval($item['duration']) : 0;

                    $movie_cinema_language = $this->isNotNull($item['language']);
                    
                }

                $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
        });
    }

    function _kinoteka(){
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINOTEKA);

        for ($x = self::DAYS_START_FROM_TODAY;  $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(self::TIMEZONE);
            $date->add(new DateInterval('P'.$x.'D'));//('P30D'));
            $date = $date->format(self::DATE_MOVIE_SEARCH_FORMAT);//date("d-m-Y");//now
            $this->getMoviesFromKinoteka($cinema, $date);
        }

        return true;
    }

    function getMoviesFromKinoteka($cinema, $date){
        $client = new Client();

        $crawler = $client->request('GET', $this->getKinotekaMoviesURL($date));
        
        //CREATE CINEMA LOCATIONS
        $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', self::KINOTEKA_DATA['id'])
                                        ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                                        ->first();
        //if locations does not exist, we'll create it
        if(is_null($cinemaLocation)){ 
            //create locations of the cinema
            $cinemaLocation = CinemaLocation::create([
                CinemaLocation::LOCATION_ID => self::KINOTEKA_DATA['id'],
                CinemaLocation::NAME => self::KINOTEKA_DATA['location'],
                CinemaLocation::CINEMA_ID => $cinema->id,
                CinemaLocation::CITY => self::KINOTEKA_DATA['city'],

            ]);
        }

        $movie = $crawler
                ->filter("div.listItem")
                ->each(function ($node) use ($cinemaLocation, $cinema, $client, $date) {
                    $linkCinemaMoviePage = self::KINOTEKA_BASE_URL.$node->filter("div.m a")->attr('href');

                    //----- Second crawling ------
                    $secondCrawler = $client->request('GET', $linkCinemaMoviePage);
                    $movieDesc = $secondCrawler
                                ->filter("div.text")
                                ->each(function ($node2) use ($cinemaLocation, $cinema, $date, $linkCinemaMoviePage) {                                    
                                    $movieDetails = $node2->filter("div.movieDetails div.details p")//p.p500
                                                ->each(function ($node3) {
                                                    return $this->isNodeIsNotEmptyText($node3);
                                                });

                                    $duration = 0;
                                    $original_lang = self::EMPTY_TEXT;
                                    $movie_cinema_language = self::EMPTY_TEXT;
                                    $release_year = self::EMPTY_TEXT;
                                    $genre = self::EMPTY_TEXT;
                                    for ($x = 0; $x < sizeof($movieDetails); $x++) {
                                        if($movieDetails[$x] === "Czas trwania:"){ //duration
                                            $duration = intval(explode(" ", $movieDetails[$x + 1])[0]);//extract duration int value
                                        }
                                        if($movieDetails[$x] === "Wersja językowa:"){//original lang
                                            $movie_cinema_language = $movieDetails[$x + 1];
                                        }
                                        if($movieDetails[$x] === "Rok produkcji:"){//release_year
                                            $release_year = $movieDetails[$x + 1];
                                        }
                                        if($movieDetails[$x] === "Gatunek:"){//genre
                                            $genre = ($movieDetails[$x + 1] === "dokument") ? "Dokumentalny" : $movieDetails[$x + 1];
                                        }
                                    }

                                    $classification = explode(" ", $this->isNodeIsNotEmptyAttr($node2->filter("div.icons span.icon"), 'title'))[1]; //Extract number from text E.g. od 15 lat

                                    $movie = new Movie;
                                    $movie->title = $this->isNodeIsNotEmptyText($node2->filter("div.movieDetails div.details p.head1"));
                                    $movie->description = $this->isNodeIsNotEmptyText($node2->filter("div.movieDesc"));
                                    $movie->duration = $duration; 
                                    $movie->original_lang = (strpos($movie_cinema_language, "angielsk") !== false) ? self::LANGUAGES_TO_PL['english'] : $movie_cinema_language;//extend this for other languages
                                    $movie->genre = $genre;
                                    $movie->classification = ($classification !== self::EMPTY_TEXT) ? $classification."+" : self::EMPTY_TEXT;
                                    $movie->release_year = $release_year;
                                    $movie->poster_url = self::KINOTEKA_BASE_URL.$this->isNodeIsNotEmptyAttr($node2->filter("div.movieDetails a.brochure"),'href');
                                    $movie->trailer_url = $this->isNodeIsNotEmptyAttr($node2->filter("div.movieTrailerPhoto div.movie iframe"), 'src');

                                    $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
                                });

                });
    }

    function _insertMovie($cinemaId, $locationId, $linkCinemaMoviePage, Movie $movieToInsert, $date, $language){
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
                    MoviesInCinema::CINEMA_MOVIE_URL => $linkCinemaMoviePage,
                    MoviesInCinema::LANGUAGE => $language
                ]);
                // echo($moviesInCinema);
            }
        } else { //if the movie already exists

            //We update movie values in case the new movie has them
            $updateValues = false;

            if($movie->title === self::EMPTY_TEXT && $movieToInsert->title !== self::EMPTY_TEXT){
                $movie->title = $movieToInsert->title;
                $updateValues = true;
            }

            if($movie->description === self::EMPTY_TEXT && $movieToInsert->description !== self::EMPTY_TEXT){
                $movie->description = $movieToInsert->description;
                $updateValues = true;
            }

            if($movie->duration === 0 && $movieToInsert->duration > 0){
                $movie->duration = $movieToInsert->duration;
                $updateValues = true;
            }

            if($movie->original_lang === self::EMPTY_TEXT && $movieToInsert->original_lang !== self::EMPTY_TEXT){
                $movie->original_lang = $movieToInsert->original_lang;
                $updateValues = true;
            }

            if($movie->genre === self::EMPTY_TEXT && $movieToInsert->genre !== self::EMPTY_TEXT){
                $movie->genre = $movieToInsert->genre;
                $updateValues = true;
            }

            if($movie->classification === self::EMPTY_TEXT && $movieToInsert->classification !== self::EMPTY_TEXT){
                $movie->classification = $movieToInsert->classification;
                $updateValues = true;
            }

            if($movie->release_year === self::EMPTY_TEXT && $movieToInsert->release_year !== self::EMPTY_TEXT){
                $movie->release_year = $movieToInsert->release_year;
                $updateValues = true;
            }

            if($movie->trailer_url === self::EMPTY_TEXT && $movieToInsert->trailer_url !== self::EMPTY_TEXT){
                $movie->trailer_url = $movieToInsert->trailer_url;
                $updateValues = true;
            }

            if($movie->poster_url === self::EMPTY_TEXT && $movieToInsert->poster_url !== self::EMPTY_TEXT){
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
                    MoviesInCinema::CINEMA_MOVIE_URL => $linkCinemaMoviePage,
                    MoviesInCinema::LANGUAGE => $language
                ]);
            }
        }
    }

    function _getLanguageFromMultikino($value){
        $attrs = array();
        foreach ($value["WhatsOnAlphabeticCinemas"] as $key => $item) {   
            foreach ($item["WhatsOnAlphabeticShedules"] as $key2 => $item2) {
                $tmp = explode(", ", $item2['VersionTitle']);
                              
                array_push($attrs, $tmp[0], $tmp[1]);
            }
        }
        return array_unique($attrs);
    }

    function getMultikinoMoviesURL($cinemaId, $date){
        // 'https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=12-08-2020&type=PRZEDSPRZEDAŻ'
        return Http::get("https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=".$cinemaId."&data=".$date);
    }

    function getMultikinoCinemasURL(){
        return Http::get(self::MULTIKINO_BASE_URL."/data/locations/");
    }

    function getCinemaCityMoviesURL($cinemaId, $date, $language){
        return Http::get(self::CINEMACITY_BASE_URL."pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/".$cinemaId."/at-date/".$date."?attr=&lang=".$language);
    }

    function getCinemaCityCinemasURL($date, $language){
        return Http::get(self::CINEMACITY_BASE_URL."pl/data-api-service/v1/quickbook/10103/cinemas/with-event/until/".$date."?attr=&lang=".$language);
    }

    function getKinoMoranowMoviesURL($date){
        return self::KINO_MORANOW_BASE_URL."repertuar?month=".$date;
    }

    function getKinotekaMoviesURL($date){
        //https://kinoteka.pl/repertuar/date,2020-09-12
        return self::KINOTEKA_BASE_URL."/repertuar/date,".$date;
    }

    function isNotNull($item){
        return isset($item) ? $item : self::EMPTY_TEXT;
    }
    
    function isNodeIsNotEmptyText($node){
        return ($node->count() > 0) ? $node->text() : self::EMPTY_TEXT;
    }

    function isNodeIsNotEmptyAttr($node, $attribute){
        return ($node->count() > 0) ? $node->attr($attribute) : self::EMPTY_TEXT;
    }

}