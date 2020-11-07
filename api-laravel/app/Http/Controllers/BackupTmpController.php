<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Movie;
use App\Cinema;
use App\MoviesInCinema;
use App\CinemaLocation;

use DateTime;
use DateInterval;
use Exception;

class BackupTmpController extends BaseController
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

    const DAYS_IN_ADVANCE = 10;
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
            "Bydgoszcz" => [53.12703, 17.989472],
            "Czechowice-Dziedzice" => [49.911823, 18.997382],
            "Elbląg" => [54.185447, 19.406908],
            "Gdańsk" => [54.372236, 18.627182],
            "Jaworzno" => [50.20359, 19.269822],
            "Katowice" => [50.259533, 19.017707],
            "Kielce" => [50.87553, 20.63582],
            "Koszalin" => [54.177535, 16.200278],
            "Kraków" => [50.089242, 19.984784],
            "Lublin" => [51.267412, 22.571349],
            "Łódź" => [51.75917, 19.460514],
            "Olsztyn" => [53.754773, 20.485013],
            "Poznań Malta" => [52.401044, 16.958479],
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
            "Tychy" => [50.111649, 18.987418],
            "Warszawa Młociny" => [52.295922, 20.93162],
            "Warszawa Targówek" => [52.302543, 21.05757],
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

        $linkCinemaMoviePage = "https://www.cinema-city.pl/filmy/wonder-woman-1984/3590s2r";
        $faker = \Faker\Factory::create();

        for ($x = 0; $x < 5; $x++) {
            $movie = new Movie([
                Movie::ID => $faker->randomNumber(3),
                Movie::TITLE => Str::random(8),
                Movie::DESCRIPTION => $faker->paragraph,
                Movie::ORIGINAL_LANG => self::LANGUAGES[0],
                Movie::DURATION => $faker->randomNumber(2),
                Movie::GENRE => Str::random(5),
                Movie::CLASSIFICATION => Str::random(6),
                Movie::YEAR => $faker->randomNumber(4),
                Movie::TRAILER => "https://www.youtube.com/watch?v=WDkg3h8PCVU&ab_channel=WarnerBros.Pictures",
                Movie::POSTER => "https://m.media-amazon.com/images/M/MV5BOTk5ODg0OTU5M15BMl5BanBnXkFtZTgwMDQ3MDY3NjM@._V1_UX182_CR0,0,182,268_AL_.jpg"
            ]);

            $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, self::LANGUAGES[0]);
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

        $linkCinemaMoviePage = "https://www.cinema-city.pl/filmy/wonder-woman-1984/3590s2r";
        $faker = \Faker\Factory::create();

        for ($x = 0; $x < 5; $x++) {
            $movie = new Movie([
                Movie::ID => $faker->randomNumber(3),
                Movie::TITLE => Str::random(8),
                Movie::DESCRIPTION => $faker->paragraph,
                Movie::ORIGINAL_LANG => self::LANGUAGES[0],
                Movie::DURATION => $faker->randomNumber(2),
                Movie::GENRE => Str::random(5),
                Movie::CLASSIFICATION => Str::random(6),
                Movie::YEAR => $faker->randomNumber(4),
                Movie::TRAILER => "https://www.youtube.com/watch?v=WDkg3h8PCVU&ab_channel=WarnerBros.Pictures",
                Movie::POSTER => "https://www.cinema-city.pl/xmedia-cw/repo/feats/posters/3590S2R-lg.jpg"
            ]);

            $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, self::LANGUAGES[0]);
        }

        return true;
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

        $linkCinemaMoviePage = "https://www.cinema-city.pl/filmy/monster-hunter/4078s2r";
        $faker = \Faker\Factory::create();

        for ($x = 0; $x < 5; $x++) {
            $movie = new Movie([
                Movie::ID => $faker->randomNumber(3),
                Movie::TITLE => Str::random(8),
                Movie::DESCRIPTION => $faker->paragraph,
                Movie::ORIGINAL_LANG => self::LANGUAGES[0],
                Movie::DURATION => $faker->randomNumber(2),
                Movie::GENRE => Str::random(5),
                Movie::CLASSIFICATION => Str::random(6),
                Movie::YEAR => $faker->randomNumber(4),
                Movie::TRAILER => "https://www.youtube.com/embed/Nfe2ntXhnnQ?hd=1&wmode=opaque&controls=1&showinfo=0&autoplay=1",
                Movie::POSTER => "https://www.cinema-city.pl/xmedia-cw/repo/feats/posters/4078S2R-lg.jpg"
            ]);

            $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, self::LANGUAGES[0]);
        }
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

        $linkCinemaMoviePage = "https://www.cinema-city.pl/filmy/smierc-na-nilu/4254s2r";
        $faker = \Faker\Factory::create();

        for ($x = 0; $x < 5; $x++) {
            $movie = new Movie([
                Movie::ID => $faker->randomNumber(3),
                Movie::TITLE => Str::random(8),
                Movie::DESCRIPTION => $faker->paragraph,
                Movie::ORIGINAL_LANG => self::LANGUAGES[0],
                Movie::DURATION => $faker->randomNumber(2),
                Movie::GENRE => Str::random(5),
                Movie::CLASSIFICATION => Str::random(6),
                Movie::YEAR => $faker->randomNumber(4),
                Movie::TRAILER => "https://www.youtube.com/embed/XuqV0Kp2Mxo?hd=1&wmode=opaque&controls=1&showinfo=0&autoplay=1",
                Movie::POSTER => "https://www.cinema-city.pl/xmedia-cw/repo/feats/posters/4254S2R-lg.jpg"
            ]);

            $this->_insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, self::LANGUAGES[0]);
        }
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
}
