<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use App\Movie;
use App\Cinema;
use App\CinemaLocation;
use App\Helpers\Utils;

use DateTime;
use DateInterval;

class CinemaBackupController
{
    const CINEMACITY = "Cinema City";
    const CINEMACITY_BASE_URL = "https://www.cinema-city.pl/";
    const EMPTY_TEXT = "";

    const POLISH_LANGUAGE_CODE = "pl_PL";

    public function backup()
    {
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::CINEMACITY);
        $language = self::POLISH_LANGUAGE_CODE;
        $status = false;

        for ($x = BackupController::DAYS_START_FROM_TODAY; $x <= BackupController::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(BackupController::TIMEZONE);
            $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
            $date = $date->format(BackupController::DATE_MOVIE_SEARCH_FORMAT); //date("d-m-Y");//now
            $status = $this->getMoviesFromCinemaCity($cinema, $date, self::POLISH_LANGUAGE_CODE);
        }

        return $status;
    }

    private function getMoviesFromCinemaCity($cinema, $date, $language)
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
                $movie->title = Utils::isNotNull($item['name']);
                $movie->description = self::EMPTY_TEXT;
                $movie->duration = ($item['length'] !== self::EMPTY_TEXT) ? intval($item['length']) : 0;
                $movie->original_lang = $original_language;
                $movie->genre = $genre;
                $movie->classification = $classification;
                $movie->release_year = Utils::isNotNull($item['releaseYear']);
                $movie->poster_url = Utils::isNotNull($item['posterLink']);
                $movie->trailer_url = Utils::isNotNull($item['videoLink']);

                Utils::insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movie_cinema_language);
            }
        }

        return true;
    }

    private function getOriginalLanguageCinemaCity($attr)
    {
        if (Str::contains($attr, "original-lang-en")) { //e.g. original-lang-en-us
            return BackupController::LANGUAGES_TO_PL['english'];
        } else if (Str::contains($attr, "original-lang-pl")) {
            return BackupController::LANGUAGES_TO_PL['polish'];
        } else if (Str::contains($attr, "original-lang-fr")) {
            return BackupController::LANGUAGES_TO_PL['french'];
        } else if (Str::contains($attr, "original-lang-cs")) {
            return BackupController::LANGUAGES_TO_PL['czech'];
        } else {
            return $attr;
        }
    }

    private function getCinemaCityMoviesURL($cinemaId, $date, $language)
    {
        return Http::get(self::CINEMACITY_BASE_URL . "pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/" . $cinemaId . "/at-date/" . $date . "?attr=&lang=" . $language);
    }

    private function getCinemaCityCinemasURL($date, $language)
    {
        return Http::get(self::CINEMACITY_BASE_URL . "pl/data-api-service/v1/quickbook/10103/cinemas/with-event/until/" . $date . "?attr=&lang=" . $language);
    }
}
