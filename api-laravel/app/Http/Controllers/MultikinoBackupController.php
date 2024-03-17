<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;

use App\Movie;
use App\Cinema;
use App\CinemaLocation;
use App\Helpers\Utils;

use DateTime;

class MultikinoBackupController
{
    const MULTIKINO = "Multikino";

    const MULTIKINO_DATA = [
        [
            "id" => "biala-podlaska",
            "city" => "Biała Podlaska",
            "coordinates" => [52.034575105624235, 23.1230369]
        ],
        [
            "id" => "bydgoszcz",
            "city" => "Bydgoszcz",
            "coordinates" => [53.12703, 17.989472]
        ],
        [
            "id" => "czechowice-dziedzice",
            "city" => "Czechowice-Dziedzice",
            "coordinates" => [49.911823, 18.997382]
        ],
        [
            "id" => "elblag",
            "city" => "Elbląg",
            "coordinates" => [54.185447, 19.406908]
        ],
        [
            "id" => "gdansk",
            "city" => "Gdańsk",
            "coordinates" => [54.372236, 18.627182]
        ],
        [
            "id" => "glogow",
            "city" => "Głogów",
            "coordinates" => [51.65027052196845, 16.054879912690154]
        ],
        [
            "id" => "gorzow-wielkopolski",
            "city" => "Gorzów Wielkopolski",
            "coordinates" => [52.761189698316905, 15.260447070416646]
        ],
        [
            "id" => "jaworzno",
            "city" => "Jaworzno",
            "coordinates" => [50.20359, 19.269822]
        ],
        [
            "id" => "kalisz",
            "city" => "Kalisz",
            "coordinates" => [51.76581245071046, 18.087820056981588]
        ],
        [
            "id" => "katowice",
            "city" => "Katowice",
            "coordinates" => [50.259533, 19.017707]
        ],
        [
            "id" => "kielce",
            "city" => "Kielce",
            "coordinates" => [50.87553, 20.63582]
        ],
        [
            "id" => "klodzko",
            "city" => "Kłodzko",
            "coordinates" => [50.454025492182424, 16.637777797289242]
        ],
        [
            "id" => "koszalin",
            "city" => "Koszalin",
            "coordinates" => [54.177535, 16.200278]
        ],
        [
            "id" => "krakow",
            "city" => "Kraków",
            "coordinates" => [50.089242, 19.984784]
        ],
        [
            "id" => "leszno",
            "city" => "Leszno",
            "coordinates" => [51.8259362917827, 16.600024612698736]
        ],
        [
            "id" => "lublin",
            "city" => "Lublin",
            "coordinates" => [51.267412, 22.571349]
        ],
        [
            "id" => "lodz",
            "city" => "Łódź",
            "coordinates" => [51.75917, 19.460514]
        ],
        [
            "id" => "mielec",
            "city" => "Mielec",
            "coordinates" => [50.286610785409415, 21.460258339609982]
        ],
        [
            "id" => "olsztyn",
            "city" => "Olsztyn",
            "coordinates" => [53.754773, 20.485013]
        ],
        [
            "id" => "poznan-multikino-51",
            "city" => "Poznań Multikino 51",
            "coordinates" => [52.399293, 16.929253]
        ],
        [
            "id" => "pruszkow",
            "city" => "Pruszków",
            "coordinates" => [52.165146, 20.792661]
        ],
        [
            "id" => "radom",
            "city" => "Radom",
            "coordinates" => [51.405484, 21.154285]
        ],
        [
            "id" => "rumia",
            "city" => "Rumia",
            "coordinates" => [54.56354, 18.389998]
        ],
        [
            "id" => "rybnik",
            "city" => "Rybnik",
            "coordinates" => [50.094443, 18.543343]
        ],
        [
            "id" => "rzeszow",
            "city" => "Rzeszów",
            "coordinates" => [50.027718, 22.0134]
        ],
        [
            "id" => "slupsk",
            "city" => "Słupsk",
            "coordinates" => [54.454135, 16.991899]
        ],
        [
            "id" => "sopot",
            "city" => "Sopot",
            "coordinates" => [54.445271, 18.567722]
        ],
        [
            "id" => "szczecin",
            "city" => "Szczecin",
            "coordinates" => [53.433928, 14.555905]
        ],
        [
            "id" => "swidnica",
            "city" => "Świdnica",
            "coordinates" => [50.84085109691801, 16.497510226143696]
        ],
        [
            "id" => "swinoujscie",
            "city" => "Świnoujście",
            "coordinates" => [53.91001029060524, 14.246391510954364]
        ],
        [
            "id" => "tarnow",
            "city" => "Tarnów",
            "coordinates" => [50.000767074418235, 20.957626768431766]
        ],
        [
            "id" => "tychy-city-point",
            "city" => "Tychy City Point",
            "coordinates" => [50.11174093713318, 18.98805127028631]
        ],
        [
            "id" => "tychy-gemini-park",
            "city" => "Tychy Gemini Park",
            "coordinates" => [50.09717906729158, 19.0086663837789]
        ],
        [
            "id" => "warszawa-atrium-reduta",
            "city" => "Warszawa Atrium Reduta",
            "coordinates" => [52.21328140079308, 20.951135897375696]
        ],
        [
            "id" => "warszawa-atrium-targowek",
            "city" => "Warszawa Atrium Targówek",
            "coordinates" => [52.30251826296771, 21.057606955051302]
        ],
        [
            "id" => "warszawa-mlociny",
            "city" => "Warszawa Młociny",
            "coordinates" => [52.295922, 20.93162]
        ],
        [
            "id" => "warszawa-ursynow",
            "city" => "Warszawa Ursynów",
            "coordinates" => [52.149941, 21.046973]
        ],
        [
            "id" => "warszawa-wola-park",
            "city" => "Warszawa Wola Park",
            "coordinates" => [52.241896, 20.932863]
        ],
        [
            "id" => "warszawa-zlote-tarasy",
            "city" => "Warszawa Złote Tarasy",
            "coordinates" => [52.229525, 21.002011]
        ],
        [
            "id" => "wloclawek",
            "city" => "Włocławek",
            "coordinates" => [52.654833, 19.060865]
        ],
        [
            "id" => "wroclaw-pasaz-grunwaldzki",
            "city" => "Wrocław Pasaż Grunwaldzki",
            "coordinates" => [51.111982, 17.05918]
        ],
        [
            "id" => "zabrze",
            "city" => "Zabrze",
            "coordinates" => [50.317464, 18.777023]
        ],
        [
            "id" => "zgorzelec",
            "city" => "Zgorzelec",
            "coordinates" => [51.153404, 15.027501]
        ]
    ];

    public function backup()
    {
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::MULTIKINO);
        $status = false;

        for ($x = BackupController::DAYS_START_FROM_TODAY; $x <= BackupController::DAYS_IN_ADVANCE; $x++) {
            $d = new DateTime(BackupController::TIMEZONE);
            $date = $d->format(BackupController::DATE_MOVIE_SEARCH_FORMAT);
            $dateSearch = date(BackupController::DATE_MOVIE_SEARCH_FORMAT, strtotime("+$x days"));
            $status = $this->getMoviesFromMultikino($cinema, $dateSearch, $date);
        }

        return $status;
    }

    private function getMoviesFromMultikino($cinema, $dateSearch, $date)
    {

        $responseCinemas = $this->getMultikinoCinemas();

        if ($responseCinemas == null) {
            Log::info("responseCinemas->failed()");
            return false;
        }

        Log::info("New List" . $dateSearch);
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
                $responseMovies = $this->getMultikinoMoviesURL($cinema->website, $cinemaLocation->location_id, $dateSearch);
                if ($responseMovies->failed())
                    return false;

                foreach ($responseMovies["WhatsOnAlphabeticFilms"] as $key => $item) {
                    $filmParams = collect($item['FilmParams'])->pluck('Title');
                    $durationFromFilmParams = $filmParams->pop();

                    $genreFromFilmParams = $filmParams->filter(function ($value, $key) {
                        return (!Str::contains($value, "Od lat"));
                    })->reduce(function ($carry, $item) {
                        return $carry . "|" . $item;
                    }, BackupController::EMPTY_TEXT);

                    $linkCinemaMoviePage = $cinema->website . $item['FilmUrl'];

                    $movie = new Movie;
                    $movie->title = Str::contains(Utils::isNotNull($item['Title']), '(Hit') ? Str::substr(Utils::isNotNull($item['Title']), 0, strpos(Utils::isNotNull($item['Title']), '(Hit')) : Utils::isNotNull($item['Title']); //We remove the "(Hit za ...) in title Multikino usually uses"
                    $movie->description = Utils::isNotNull($item['ShortSynopsis']);
                    $movie->duration = Str::contains($durationFromFilmParams, "minut") ? explode(" ", $durationFromFilmParams)[0] : 0; //extract movie duration value

                    $movie->genre = $genreFromFilmParams;
                    $movie->classification = ($item['CertificateAge'] !== BackupController::EMPTY_TEXT) ? $item['CertificateAge'] . "+" : $item['CertificateAge'];
                    $movie->release_year = BackupController::EMPTY_TEXT;
                    $movie->poster_url = Utils::isNotNull($item['Poster']);
                    $movie->trailer_url = Utils::isNotNull($item['TrailerUrl']);

                    $multikinoLangs = $this->_getLanguageFromMultikino($item['WhatsOnAlphabeticCinemas'][0]);
                    $movie_cinema_language = $multikinoLangs["languageDescription"];
                    $movie->original_lang = $multikinoLangs["original_lang"];

                    Utils::insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
                }
            }
        }
        return true;
    }

    private function _getLanguageFromMultikino($value)
    {
        $originalLang = BackupController::EMPTY_TEXT;
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

    function getMultikinoMoviesURL($baseUrl, $cinemaId, $date)
    {
        return Http::get($baseUrl . "api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=" . $cinemaId . "&data=" . $date);
    }

    function getMultikinoCinemas()
    {
        // Multikino locations as date 14/03/2024
        try {
            // Path to your JSON file within the storage directory
            $filePath = 'app/json/MultikinoLocations.json';
            $jsonContents = file_get_contents(base_path('storage/' . $filePath));

            // Decode the JSON contents into an associative array
            // $data = json_decode($jsonContents, true);

            return json_decode($jsonContents, true);
        } catch (FileNotFoundException $e) {
            // Handle the file not found exception
            // For example, log the error or show a message to the user
            echo "File not found: " . $e->getMessage();
            return null;
        } catch (\Exception $e) {
            // Handle other exceptions
            echo "An error occurred: " . $e->getMessage();
            return null;
        }
    }
}
