<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Movie;
use App\Cinema;
use App\MoviesInCinema;
use App\CinemaLocation;

use DateTime;
use DateInterval;
use Goutte\Client;
use Exception;

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

    const DAYS_IN_ADVANCE = 15;
    const TIMEZONE = "Europe/Warsaw";
    const DAYS_START_FROM_TODAY = 0;
    const DAYS_START_FROM_TOMORROW = 1;
    const DATE_MOVIE_SEARCH_FORMAT = 'Y-m-d';

    const LANGUAGES_TO_PL = [
        "english" => "angielski",
        "polish" => "polski",
        "french" => "francuski",
        "czech" => "czeski"
    ];

    const POLISH_LANGUAGE_CODE = "pl_PL";

    const KINO_MURANOW_DATA = [
        "id" => 156,
        "city" => "Warszawa",
        "location" => "Warszawa",
        "coord_latitude" => "52.245339",
        "coord_longitude" => "20.998964"
    ];

    const KINOTEKA_DATA = [
        "id" => 35,
        "city" => "Warszawa",
        "location" => "Warszawa",
        "coord_latitude" => "52.231816",
        "coord_longitude" => "21.005839"
    ];

    const MULTIKINO_DATA = [
        "coordinates" => [
            "Biała Podlaska" => [52.034575105624235, 23.1230369],
            "Bydgoszcz" => [53.12703, 17.989472],
            "Czechowice-Dziedzice" => [49.911823, 18.997382],
            "Elbląg" => [54.185447, 19.406908],
            "Gdańsk" => [54.372236, 18.627182],
            "Głogów" => [51.65027052196845, 16.054879912690154],
            "Gorzów Wielkopolski" => [52.761189698316905, 15.260447070416646],
            "Jaworzno" => [50.20359, 19.269822],
            "Kalisz"=> [51.76581245071046, 18.087820056981588],
            "Katowice" => [50.259533, 19.017707],
            "Kielce" => [50.87553, 20.63582],
            "Kłodzko"=> [50.454025492182424, 16.637777797289242],
            "Koszalin" => [54.177535, 16.200278],
            "Kraków" => [50.089242, 19.984784],
            "Leszno"=> [51.8259362917827, 16.600024612698736],
            "Lublin" => [51.267412, 22.571349],
            "Łódź" => [51.75917, 19.460514],
            "Mielec"=> [50.286610785409415, 21.460258339609982],
            "Olsztyn" => [53.754773, 20.485013],
            "Poznań Multikino 51" => [52.399293, 16.929253],
            "Poznań Stary Browar" => [52.402964, 16.924386],
            "Pruszków" => [52.165146, 20.792661],
            "Radom" => [51.405484, 21.154285],
            "Rumia" => [54.56354, 18.389998],
            "Rybnik" => [50.094443, 18.543343],
            "Rzeszów" => [50.027718, 22.0134],
            "Słupsk" => [54.454135, 16.991899],
            "Sopot" => [54.445271, 18.567722],
            "Szczecin" => [53.433928, 14.555905],
            "Świdnica"=> [50.84085109691801, 16.497510226143696],
            "Świnoujście"=> [53.91001029060524, 14.246391510954364],
            "Tarnów"=> [50.000767074418235, 20.957626768431766],
            "Tychy City Point" => [50.11174093713318, 18.98805127028631],
            "Tychy Gemini Park"=> [50.09717906729158, 19.0086663837789],
            "Warszawa Atrium Reduta"=> [52.21328140079308, 20.951135897375696],
            "Warszawa Atrium Targówek" => [52.30251826296771, 21.057606955051302],
            "Warszawa Młociny" => [52.295922, 20.93162],
            "Warszawa Ursynów" => [52.149941, 21.046973],
            "Warszawa Wola Park" => [52.241896, 20.932863],
            "Warszawa Złote Tarasy" => [52.229525, 21.002011],
            "Włocławek" => [52.654833, 19.060865],
            "Wrocław Pasaż Grunwaldzki" => [51.111982, 17.05918],
            "Zabrze" => [50.317464, 18.777023],
            "Zgorzelec" => [51.153404, 15.027501]
        ]
    ];

    const LANGUAGES = [
        "angielski",
        "arabski",
        "czeski",
        "francuski",
        "hiszpański",
        "islandzki",
        "japoński",
        "niemiecki",
        "polskie",
        "polski dubbing",
        "polski lektor",
        "portugalski",
        "rosyjski",
        "sycylijski",
        "ukraiński",
        "włoski"
    ];


    const EMPTY_TEXT = "";

    public function backupData()
    {
        //clean previous saved data
        DB::table(Movie::TABLE_NAME)->delete();
        DB::table(MoviesInCinema::TABLE_NAME)->delete();
        DB::table(CinemaLocation::TABLE_NAME)->delete();

        //call and save new data
        $successMultikino = $this->_multikino();

        $successCinemacity = $this->_cinemacity();

        $successKinoteka = $this->_kinoteka();

        $successKinoMoranow = $this->_kinoMoranow();

        // if($successMultikino)
        if ($successMultikino && $successCinemacity && $successKinoMoranow && $successKinoteka)
            return $this->sendResponse(self::EMPTY_TEXT, 'Backup completed successfully.');
        else
            return $this->sendError('Backup could not be completed.', 500);
    }

    function _multikino()
    {

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::MULTIKINO);
        $status = false;
        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $d = new DateTime(self::TIMEZONE);
            $d->add(new DateInterval('P' . $x . 'D')); //('P30D'));
            $dateSearch = $d->format('d-m-Y'); //date("d-m-Y");//now
            $date = $d->format(self::DATE_MOVIE_SEARCH_FORMAT);
            $status = $this->getMoviesFromMultikino($cinema, $dateSearch, $date);
        }

        return $status;
    }

    function getMoviesFromMultikino($cinema, $dateSearch, $date)
    {
        $responseCinemas = $this->getMultikinoCinemasURL();
        if ($responseCinemas->failed())
            return false;

        foreach ($responseCinemas["venues"] as $keyC => $itemC) {
            foreach ($itemC["cinemas"] as $keyD => $itemD) {
                //CREATE CINEMA LOCATIONS
                $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', $itemD['id'])
                    ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                    ->first();
                //if locations does not exist, we'll create it
                if (is_null($cinemaLocation)) {
                    $city = explode(" ", $itemD['search']);
                    $locationName = trim($itemD['search']);
                    $city_code = Str::lower(str_replace(" ", "_", $city[0]));
                    //create locations of the cinema
                    $cinemaLocation = CinemaLocation::create([
                        CinemaLocation::LOCATION_ID => $itemD['id'],
                        CinemaLocation::NAME => $locationName,
                        CinemaLocation::CITY => $city[0],
                        CinemaLocation::CITY_CODE => $city_code,
                        CinemaLocation::CINEMA_ID => $cinema->id,
                        CinemaLocation::COORD_LATITUDE => self::MULTIKINO_DATA['coordinates'][$locationName][0], //remove whitespaces in item search and use it as indexes
                        CinemaLocation::COORD_LONGITUDE => self::MULTIKINO_DATA['coordinates'][$locationName][1],
                    ]);
                }

                //GET movies from the selected cinema location
                $responseMovies = $this->getMultikinoMoviesURL($cinemaLocation->location_id, $dateSearch);
                if ($responseMovies->failed())
                    return false;

                foreach ($responseMovies["WhatsOnAlphabeticFilms"] as $key => $item) {
                    $filmParams = collect($item['FilmParams'])->pluck('Title');
                    $durationFromFilmParams = $filmParams->pop();

                    $genreFromFilmParams = $filmParams->filter(function ($value, $key) {
                        return (!Str::contains($value, "Od lat"));
                    })->reduce(function ($carry, $item) {
                        return $carry . "|" . $item;
                    }, self::EMPTY_TEXT);

                    $linkCinemaMoviePage = self::MULTIKINO_BASE_URL . $item['FilmUrl'];

                    $movie = new Movie;
                    $movie->title = Str::contains($this->isNotNull($item['Title']), '(Hit') ? Str::substr($this->isNotNull($item['Title']), 0, strpos($this->isNotNull($item['Title']), '(Hit')) : $this->isNotNull($item['Title']); //We remove the "(Hit za ...) in title Multikino usually uses"
                    $movie->description = $this->isNotNull($item['ShortSynopsis']);
                    $movie->duration = Str::contains($durationFromFilmParams, "minut") ? explode(" ", $durationFromFilmParams)[0] : 0; //extract movie duration value

                    $movie->genre = $genreFromFilmParams;
                    $movie->classification = ($item['CertificateAge'] !== self::EMPTY_TEXT) ? $item['CertificateAge'] . "+" : $item['CertificateAge'];
                    $movie->release_year = self::EMPTY_TEXT;
                    $movie->poster_url = $this->isNotNull($item['Poster']);
                    $movie->trailer_url = $this->isNotNull($item['TrailerUrl']);

                    $multikinoLangs = $this->_getLanguageFromMultikino($item['WhatsOnAlphabeticCinemas'][0]);
                    $movie_cinema_language = $multikinoLangs["languageDescription"];
                    $movie->original_lang = $multikinoLangs["original_lang"];

                    $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
                }
            }
        }
        return true;
    }

    function _cinemacity()
    {

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::CINEMACITY);
        $language = self::POLISH_LANGUAGE_CODE;
        $status = false;

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(self::TIMEZONE);
            $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
            $date = $date->format(self::DATE_MOVIE_SEARCH_FORMAT); //date("d-m-Y");//now
            $status = $this->getMoviesFromCinemaCity($cinema, $date, self::POLISH_LANGUAGE_CODE);
        }

        return $status;
    }

    function getMoviesFromCinemaCity($cinema, $date, $language)
    {
        $responseCinemas = $this->getCinemaCityCinemasURL($date, $language);
        if ($responseCinemas->failed())
            return false;

        foreach ($responseCinemas["body"]["cinemas"] as $keyC => $itemC) {
            //CREATE CINEMA LOCATIONS
            $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', $itemC['id'])
                ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
                ->first();
            //if locations does not exist, we'll create it
            if (is_null($cinemaLocation)) {
                $city_code = Str::lower(str_replace(" ", "_", $itemC['addressInfo']['city']));
                //create locations of the cinema
                $cinemaLocation = CinemaLocation::create([
                    CinemaLocation::LOCATION_ID => $itemC['id'],
                    CinemaLocation::NAME => $itemC['displayName'],
                    CinemaLocation::CITY => $itemC['addressInfo']['city'],
                    CinemaLocation::CITY_CODE => $city_code,
                    CinemaLocation::CINEMA_ID => $cinema->id,
                    CinemaLocation::COORD_LATITUDE => $itemC['latitude'],
                    CinemaLocation::COORD_LONGITUDE => $itemC['longitude']
                ]);
            }

            $websiteCinema = $itemC['link'];
            $cinemaID = $itemC['id'];

            //GET movies from the selected cinema location
            $responseMovies = $this->getCinemaCityMoviesURL($cinemaLocation->location_id, $date, $language);
            if ($responseMovies->failed())
                return false;

            foreach ($responseMovies["body"]["films"] as $key => $item) {
                // plus / na / bez-ograniczen --->Age restriction
                // dubbed, subbed, original-lang,first-subbed-lang ---> languages 
                $genre = self::EMPTY_TEXT;
                $original_language = self::EMPTY_TEXT;
                $movie_cinema_language = self::EMPTY_TEXT;
                $classification = self::EMPTY_TEXT;
                foreach ($item['attributeIds'] as &$attr) {
                    if (
                        !Str::contains($attr, "lang") &&
                        !Str::contains($attr, "sub") &&
                        !Str::contains($attr, "dub") &&
                        !Str::contains($attr, "2d") &&
                        !Str::contains($attr, "3d") &&
                        !Str::contains($attr, "na") &&
                        !Str::contains($attr, "plus") &&
                        !Str::contains($attr, "ograniczen")
                    ) {
                        $genre = $genre . "|" . $attr; //we saved only attributes refering to movie categories
                    }

                    if (
                        Str::contains($attr, "original-lang") ||
                        Str::contains($attr, "dubbed-lang") ||
                        Str::contains($attr, "subbed-lang")
                    ) {
                        if (Str::contains($attr, "original-lang")) {
                            $original_language = $this->getOriginalLanguageCinemaCity($attr);
                            $movie_cinema_language = ($movie_cinema_language === self::EMPTY_TEXT) ? $original_language : $original_language . "|" . $movie_cinema_language;
                        } else if (Str::contains($attr, "subbed-lang")) {
                            $sub = ($attr === "first-subbed-lang-pl") ? "polskie napisy" : $attr;
                            // $movie_cinema_language = $movie_cinema_language."(".$sub.")";
                            $movie_cinema_language = ($movie_cinema_language === self::EMPTY_TEXT) ? $sub : $movie_cinema_language . "|" . $sub;
                        } else { //"dubbed-lang"
                            $dubb = ($attr === "dubbed-lang-pl") ? "polski dubbing" : $attr;
                            $movie_cinema_language = ($movie_cinema_language === self::EMPTY_TEXT) ? $dubb : $movie_cinema_language . "|" . $dubb;
                        }
                    }

                    if (
                        Str::contains($attr, "na") &&
                        Str::contains($attr, "plus") &&
                        Str::contains($attr, "ograniczen")
                    ) {
                        $classification = $attr;
                    }
                }


                // $linkCinemaMoviePage = $item['link'];
                $linkCinemaMoviePage = $websiteCinema . "/" . $cinemaID . "#/buy-tickets-by-cinema?in-cinema=" . $cinemaLocation->id . "&at=" . $date . "&for-movie=" . $item["id"] . "&view-mode=list";

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

        return true;
    }

    function getOriginalLanguageCinemaCity($attr)
    {
        if (Str::contains($attr, "original-lang-en")) { //e.g. original-lang-en-us
            return self::LANGUAGES_TO_PL['english'];
        } else if (Str::contains($attr, "original-lang-pl")) {
            return self::LANGUAGES_TO_PL['polish'];
        } else if (Str::contains($attr, "original-lang-fr")) {
            return self::LANGUAGES_TO_PL['french'];
        } else if (Str::contains($attr, "original-lang-cs")) {
            return self::LANGUAGES_TO_PL['czech'];
        } else {
            return $attr;
        }
    }

    function _kinoMoranow()
    {

        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINO_MORANOW);

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(self::TIMEZONE);
            $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
            $date = $date->format(self::DATE_MOVIE_SEARCH_FORMAT); //date("d-m-Y");//now
            try{
                $this->getMoviesFromKinoMoranow($cinema, $date);
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    function getMoviesFromKinoMoranow($cinema, $date)
    {
        $client = new Client();

        $crawler = $client->request('GET', $this->getKinoMoranowMoviesURL($date));

        //CREATE CINEMA LOCATIONS
        $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', self::KINO_MURANOW_DATA['id'])
            ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
            ->first();
        //if locations does not exist, we'll create it
        if (is_null($cinemaLocation)) {
            $city_code = Str::lower(str_replace(" ", "_", self::KINO_MURANOW_DATA['city']));
            //create locations of the cinema
            $cinemaLocation = CinemaLocation::create([
                CinemaLocation::LOCATION_ID => self::KINO_MURANOW_DATA['id'],
                CinemaLocation::NAME => self::KINO_MURANOW_DATA['location'],
                CinemaLocation::CINEMA_ID => $cinema->id,
                CinemaLocation::CITY => self::KINO_MURANOW_DATA['city'],
                CinemaLocation::CITY_CODE => $city_code,
                CinemaLocation::COORD_LATITUDE => self::KINO_MURANOW_DATA['coord_latitude'],
                CinemaLocation::COORD_LONGITUDE => self::KINO_MURANOW_DATA['coord_longitude'],
            ]);
        }

        $movie = $crawler
            ->filter("div.rep-film-desc-wrapper")
            ->each(function ($node) use ($cinemaLocation, $cinema, $client, $date) {
                $linkCinemaMoviePage = self::KINO_MORANOW_BASE_URL . $node->filter("div.rep-film-desc-mobile a")->attr('href');

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
                        if ($durationNode->count() > 0) {
                            preg_match_all('!\d+!', $durationNode->text(), $matches); //we extract duration from string. Ex.: '110min.' -> 110
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

                $movie->poster_url = str_replace("cycle_2x1_hover", "cycle_1x1", $movie->poster_url); //adjust poster size

                foreach ($movieDesc as $key => $item) {
                    $movie->description = $this->isNotNull($item['description']);
                    $movie->trailer_url = $this->isNotNull($item['trailer_url']);
                }

                foreach ($movieDetails as $key => $item) {
                    $originalLang = Str::contains($this->isNotNull($item['language']), '(') ? Str::substr($this->isNotNull($item['language']), 0, (strpos($this->isNotNull($item['language']), '(') - 1)) : $this->isNotNull($item['language']);  //E.g., extract original lang before parenthesis: hiszpański (napisy polskie i angielskie)
                    $movie->original_lang = $originalLang; //Str::contains($originalLang, "polski dubbing") ? self::EMPTY_TEXT : $originalLang;
                    $movie->genre = $this->isNotNull($item['category']);
                    $movie->classification = self::EMPTY_TEXT;
                    $movie->release_year = $this->isNotNull($item['production_date']);
                    $movie->duration = isset($item['duration']) ? intval($item['duration']) : 0;

                    $napisy = Str::substr($this->isNotNull($item['language']), strpos($this->isNotNull($item['language']), '('), strpos($this->isNotNull($item['language']), ')'));
                    if ($napisy !== self::EMPTY_TEXT && $originalLang !== self::EMPTY_TEXT) {
                        $movie_cinema_language = $originalLang . "|" . $napisy;
                    } else if ($napisy !== self::EMPTY_TEXT && $originalLang === self::EMPTY_TEXT) {
                        $movie_cinema_language = $napisy;
                    } else if ($napisy === self::EMPTY_TEXT && $originalLang !== self::EMPTY_TEXT) {
                        $movie_cinema_language = $originalLang;
                    } else {
                        $movie_cinema_language = self::EMPTY_TEXT;
                    }

                    $movie_cinema_language = str_replace("(", "", $movie_cinema_language);
                    $movie_cinema_language = str_replace(")", "", $movie_cinema_language);
                }

                $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
            });
    }

    function _kinoteka()
    {
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINOTEKA);

        for ($x = self::DAYS_START_FROM_TODAY; $x <= self::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(self::TIMEZONE);
            $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
            $date = $date->format(self::DATE_MOVIE_SEARCH_FORMAT); //date("d-m-Y");//now
            try{
                $this->getMoviesFromKinoteka($cinema, $date);
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    function getMoviesFromKinoteka($cinema, $date)
    {
        $client = new Client();

        $crawler = $client->request('GET', $this->getKinotekaMoviesURL($date));

        //CREATE CINEMA LOCATIONS
        $cinemaLocation = CinemaLocation::where(CinemaLocation::LOCATION_ID, '=', self::KINOTEKA_DATA['id'])
            ->where(CinemaLocation::CINEMA_ID, '=', $cinema->id)
            ->first();
        //if locations does not exist, we'll create it
        if (is_null($cinemaLocation)) {
            $city_code = Str::lower(str_replace(" ", "_", self::KINOTEKA_DATA['city']));
            //create locations of the cinema
            $cinemaLocation = CinemaLocation::create([
                CinemaLocation::LOCATION_ID => self::KINOTEKA_DATA['id'],
                CinemaLocation::NAME => self::KINOTEKA_DATA['location'],
                CinemaLocation::CINEMA_ID => $cinema->id,
                CinemaLocation::CITY => self::KINOTEKA_DATA['city'],
                CinemaLocation::CITY_CODE => $city_code,
                CinemaLocation::COORD_LATITUDE => self::KINOTEKA_DATA['coord_latitude'],
                CinemaLocation::COORD_LONGITUDE => self::KINOTEKA_DATA['coord_longitude'],
            ]);
        }

        $movie = $crawler
            ->filter("div.listItem")
            ->each(function ($node) use ($cinemaLocation, $cinema, $client, $date) {
                $linkCinemaMoviePage = self::KINOTEKA_BASE_URL . $node->filter("div.m a")->attr('href');

                //----- Second crawling ------
                $secondCrawler = $client->request('GET', $linkCinemaMoviePage);
                $movieDesc = $secondCrawler
                    ->filter("div.text")
                    ->each(function ($node2) use ($cinemaLocation, $cinema, $date, $linkCinemaMoviePage) {
                        $movieDetails = $node2->filter("div.movieDetails div.details p") //p.p500
                            ->each(function ($node3) {
                                return $this->isNodeIsNotEmptyText($node3);
                            });

                        $duration = 0;
                        $original_lang = self::EMPTY_TEXT;
                        $movie_cinema_language = self::EMPTY_TEXT;
                        $release_year = self::EMPTY_TEXT;
                        $genre = self::EMPTY_TEXT;
                        for ($x = 0; $x < sizeof($movieDetails); $x++) {
                            if ($movieDetails[$x] === "Czas trwania:") { //duration
                                $duration = intval(explode(" ", $movieDetails[$x + 1])[0]); //extract duration int value
                            }
                            if ($movieDetails[$x] === "Wersja językowa:") { //original lang
                                $movie_cinema_language = $movieDetails[$x + 1];
                            }
                            if ($movieDetails[$x] === "Rok produkcji:") { //release_year
                                $release_year = $movieDetails[$x + 1];
                            }
                            if ($movieDetails[$x] === "Gatunek:") { //genre
                                $genre = ($movieDetails[$x + 1] === "dokument") ? "Dokumentalny" : $movieDetails[$x + 1];
                            }
                        }

                        $classification = $this->isNodeIsNotEmptyAttr($node2->filter("div.icons span.icon"), 'title');
                        $classification = Str::contains($classification, "lat") ? explode(" ", $classification)[1] : self::EMPTY_TEXT; //Extract number from text E.g. od 15 lat

                        $movie = new Movie;

                        $title = $this->isNodeIsNotEmptyText($node2->filter("div.movieDetails div.details p.head1"));
                        $movie->title = Str::contains($title, '(') ? Str::substr($title, 0, strpos($title, '(')) : $title; //We remove the title in PL. E.g.:"Movie Title in PL (Movie title in EN) in title Kinoteka"

                        $movie->description = $this->isNodeIsNotEmptyText($node2->filter("div.movieDesc"));
                        $movie->duration = $duration;
                        $movie->genre = $genre;
                        $movie->classification = ($classification !== self::EMPTY_TEXT) ? $classification . "+" : self::EMPTY_TEXT;
                        $movie->release_year = $release_year;
                        $movie->poster_url = self::KINOTEKA_BASE_URL . $this->isNodeIsNotEmptyAttr($node2->filter("div.movieDetails a.brochure"), 'href');
                        $movie->trailer_url = $this->isNodeIsNotEmptyAttr($node2->filter("div.movieTrailerPhoto div.movie iframe"), 'src');

                        //angielska, polska ->original_lang = "angielskie, polskie"
                        //+ angielskie ->original_lang = "napisy angielski"
                        //polski dubbing, lektor, bez dialogów ->original_lang = ""
                        //ejemplos:
                        //angielska | napisy: polskie
                        //napisy: polskie + angielskie
                        //czeska | napisy: polskie
                        //polska | napisy: brak   
                        //polski dubbing
                        //polska
                        $movie->original_lang = $original_lang;
                        if (Str::contains($movie_cinema_language, "angielsk")) {
                            if (Str::contains($movie_cinema_language, "angielska")) {
                                $movie->original_lang = self::LANGUAGES_TO_PL['english'];
                            } else if (Str::contains($movie_cinema_language, "+ angielskie")) {
                                $movie->original_lang = "język napisów: angielski";
                            }
                        } else if (Str::contains($movie_cinema_language, "polska")) {
                            $movie->original_lang = self::LANGUAGES_TO_PL['polish'];
                        } else if (Str::contains($movie_cinema_language, "lektor") || Str::contains($movie_cinema_language, "dubbing")) {
                            $movie->original_lang = $movie_cinema_language;//self::EMPTY_TEXT;
                        } else if (Str::contains($movie_cinema_language, "| napisy:")) {
                            $movie->original_lang = explode("| napisy:", $movie_cinema_language)[0];
                        }

                        $movie_cinema_language = explode("| subtitles", $movie_cinema_language)[0]; //e.g., We remove the english translation (| subt): "napisy: polskie + angielskie | subtitles: polish + english"
                        $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
                    });
            });
    }

    function _insertMovie($cinemaId, $locationId, $linkCinemaMoviePage, Movie $movieToInsert, $date, $language)
    {
        $movie = Movie::firstWhere(Movie::TITLE, '=', $movieToInsert->title);

        if (!$movie) { //if the movie does not exist           
            //first create the new movie and get the inserted ID
            //then associate the movie with the cinema
            if ($movieToInsert->save()) {
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

            if ($movie->title === self::EMPTY_TEXT && $movieToInsert->title !== self::EMPTY_TEXT) {
                $movie->title = $movieToInsert->title;
                $updateValues = true;
            }

            if ($movie->description === self::EMPTY_TEXT && $movieToInsert->description !== self::EMPTY_TEXT) {
                $movie->description = $movieToInsert->description;
                $updateValues = true;
            }

            if ($movie->duration === 0 && $movieToInsert->duration > 0) {
                $movie->duration = $movieToInsert->duration;
                $updateValues = true;
            }

            if ($movie->original_lang === self::EMPTY_TEXT && $movieToInsert->original_lang !== self::EMPTY_TEXT) {
                $movie->original_lang = $movieToInsert->original_lang;
                $updateValues = true;
            }

            if ($movie->genre === self::EMPTY_TEXT && $movieToInsert->genre !== self::EMPTY_TEXT) {
                $movie->genre = $movieToInsert->genre;
                $updateValues = true;
            }

            if ($movie->classification === self::EMPTY_TEXT && $movieToInsert->classification !== self::EMPTY_TEXT) {
                $movie->classification = $movieToInsert->classification;
                $updateValues = true;
            }

            if ($movie->release_year === self::EMPTY_TEXT && $movieToInsert->release_year !== self::EMPTY_TEXT) {
                $movie->release_year = $movieToInsert->release_year;
                $updateValues = true;
            }

            if ($movie->trailer_url === self::EMPTY_TEXT && $movieToInsert->trailer_url !== self::EMPTY_TEXT) {
                $movie->trailer_url = $movieToInsert->trailer_url;
                $updateValues = true;
            }

            if ($movie->poster_url === self::EMPTY_TEXT && $movieToInsert->poster_url !== self::EMPTY_TEXT) {
                $movie->poster_url = $movieToInsert->poster_url;
                $updateValues = true;
            }

            if ($updateValues)
                $movie->save();


            //we find if the cinema is already associated with the movie
            $moviesInCinema = MoviesInCinema::where(MoviesInCinema::MOVIE_ID, "=", $movie->id)
                ->where(MoviesInCinema::CINEMA_ID, "=", $cinemaId)
                ->where(MoviesInCinema::LOCATION_ID, "=", $locationId)
                ->where(MoviesInCinema::DAY_TITLE, "=", $date)
                ->first();

            if (!$moviesInCinema) {   //if the cinema if not associated with the movie, we create it
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

    function _getLanguageFromMultikino($value)
    {
        $originalLang = self::EMPTY_TEXT;
        $attrs = array();
        foreach ($value["WhatsOnAlphabeticCinemas"] as $key => $item) {
            foreach ($item["WhatsOnAlphabeticShedules"] as $key2 => $item2) {
                $tmp = explode(", ", $item2['VersionTitle']);
                // $tmp[0] === "2D" ---  Obviamos por el momento
                $attr = "";
                if (strtoupper($tmp[1]) === "napisy") {
                    $attr = "polskie napisy";
                    //$originalLang = "polskie napisy";
                } else if (strtoupper($tmp[1]) === "dubbing") {
                    $attr = "polskie dubbing";
                    $originalLang = "polskie dubbing";
                } else if (strtoupper($tmp[1]) === "pl") {
                    $attr = "polskie";
                    $originalLang = "polskie";
                }
                array_push($attrs, $attr);
            }
        }
        return ["original_lang" => $originalLang, "languageDescription" => implode("|", array_unique($attrs))];
    }

    function getMultikinoMoviesURL($cinemaId, $date)
    {
        // 'https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43&data=12-08-2020&type=PRZEDSPRZEDAŻ'
        return Http::get("https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=" . $cinemaId . "&data=" . $date);
    }

    function getMultikinoCinemasURL()
    {
        return Http::get(self::MULTIKINO_BASE_URL . "/data/locations/");
    }

    function getCinemaCityMoviesURL($cinemaId, $date, $language)
    {
        return Http::get(self::CINEMACITY_BASE_URL . "pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/" . $cinemaId . "/at-date/" . $date . "?attr=&lang=" . $language);
    }

    function getCinemaCityCinemasURL($date, $language)
    {
        return Http::get(self::CINEMACITY_BASE_URL . "pl/data-api-service/v1/quickbook/10103/cinemas/with-event/until/" . $date . "?attr=&lang=" . $language);
    }

    function getKinoMoranowMoviesURL($date)
    {
        return self::KINO_MORANOW_BASE_URL . "repertuar?month=" . $date;
    }

    function getKinotekaMoviesURL($date)
    {
        //https://kinoteka.pl/repertuar/date,2020-09-12
        return self::KINOTEKA_BASE_URL . "/repertuar/date," . $date;
    }

    function isNotNull($item)
    {
        return isset($item) ? $item : self::EMPTY_TEXT;
    }

    function isNodeIsNotEmptyText($node)
    {
        return ($node->count() > 0) ? $node->text() : self::EMPTY_TEXT;
    }

    function isNodeIsNotEmptyAttr($node, $attribute)
    {
        return ($node->count() > 0) ? $node->attr($attribute) : self::EMPTY_TEXT;
    }
}
