<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

use App\Movie;
use App\Cinema;
use App\MoviesInCinema;
use App\CinemaLocation;
use App\Helpers\Utils;

use DateTime;
use DateInterval;
use Exception;

class KMBackupController
{
    const KINO_MORANOW = "Kino Muranow";
    const KINO_MURANOW_DATA = array(
        "id" => 156,
        "city" => "Warszawa",
        "location" => "Warszawa",
        "coord_latitude" => "52.245339",
        "coord_longitude" => "20.998964"
    );
    const EMPTY_TEXT = "";


    public function backup()
    {
        $cinema = Cinema::firstWhere(Cinema::NAME, '=', self::KINO_MORANOW);

        for ($x = BackupController::DAYS_START_FROM_TODAY; $x <= BackupController::DAYS_IN_ADVANCE; $x++) {
            $date = (new DateTime(BackupController::TIMEZONE))->add(new DateInterval('P' . $x . 'D'))->format(BackupController::DATE_MOVIE_SEARCH_FORMAT);

            try {
                $this->getMovies($cinema, $date);
            } catch (Exception $e) {
                Log::info($e);
                return false;
            }
        }

        return true;
    }

    private function getMovies($cinema, $date)
    {
        $cinemaLocation = $this->createCinemaLocation($cinema);

        $client = new HttpBrowser(HttpClient::create(['timeout' => BackupController::HTTP_CLIENT_TIMEOUT]));
        $client->request('GET', $this->getKinoMoranowMoviesURL($cinema->website, $date));
        $html = $client->getResponse()->getContent();
        $crawler = new Crawler($html);

        $movie = $crawler
            ->filter('div.movie-calendar-info')
            ->each(function ($node) use ($cinemaLocation, $cinema, $client, $date) {
                $linkCinemaMoviePage = $node->filter('a.movie-calendar-info-expand__thumb')->attr('href');

                //to skip movies already saved
                if (!MoviesInCinema::where('cinema_id', $cinema->id)->where('cinema_movie_url', $linkCinemaMoviePage)->exists()) {
                    $secondCrawler = $client->request('GET', $linkCinemaMoviePage);

                    $movieDetails = $this->extractMovieDetails($cinema->website, $secondCrawler);

                    $movie = $this->createMovie($movieDetails);

                    $movieCinemaLanguage = $this->extractMovieLanguage($movieDetails);

                    Utils::insertMovie($cinema->id, $cinemaLocation->id, $linkCinemaMoviePage, $movie, $date, $movieCinemaLanguage);
                }
            });
    }

    private function createCinemaLocation($cinema)
    {
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

        return $cinemaLocation;
    }

    private function extractMovieDetails($baseUrl, $crawler)
    {
        $description =  $crawler->filter("div.node__desc")->text();
        if ($description == self::EMPTY_TEXT) {
            $description =  $crawler->filter("div.node__summary")->text();
        }

        $trailer = Utils::isNodeIsNotEmptyAttr($crawler->filter('iframe.youtube-player'), 'src');

        $poster = $baseUrl . Utils::isNodeIsNotEmptyAttr($crawler->filter('img.image-style-slide'), 'src');

        $durationNode = $crawler->filter('div.field--name-field-movie-duration div.field__item');
        $duration = 0;
        if ($durationNode->count() > 0) {
            preg_match_all('!\d+!', $durationNode->text(), $matches); //we extract duration from string. Ex.: '110min.' -> 110
            $duration = $matches[0][0];
        }

        return array(
            "title_pl" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-polish-title div.field__item')),
            "title_original" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-original-title div.field__item')),
            "category" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-category div.field__item')),
            "language" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-language div.field__item')),
            "direction" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-director div.field__item')),
            "production_date" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-production-year div.field__item')),
            "production_country" => Utils::isNodeIsNotEmptyText($crawler->filter('div.field--name-field-movie-production-country div.field__item')),
            "duration" => $duration,
            "description" => $description,
            "trailer_url" => $trailer,
            "poster_url" => $poster
        );
    }

    private function createMovie($movieDetails)
    {
        return new Movie(
            Utils::isNotNull($movieDetails['title_pl']),
            Str::contains(Utils::isNotNull($movieDetails['language']), '(') ? Str::before(Utils::isNotNull($movieDetails['language']), ' (') : Utils::isNotNull($movieDetails['language']),
            Utils::isNotNull($movieDetails['description']),
            Utils::isNotNull($movieDetails['poster_url']),
            Utils::isNotNull($movieDetails['trailer_url']),
            Utils::isNotNull($movieDetails['category']),
            self::EMPTY_TEXT, //classification
            Utils::isNotNull($movieDetails['production_date']),
            $movieDetails['duration']
        );
    }

    private function extractMovieLanguage($movieDetails)
    {
        $language = Utils::isNotNull($movieDetails['language']);
        $napisy = Str::contains($language, '(') ? Str::between($language, '(', ')') : '';

        if ($napisy && !Str::contains($napisy, 'polski dubbing')) {
            return Str::before($language, ' (') . '|' . $napisy;
        } else {
            return Str::before($language, ' (');
        }
    }

    private function getKinoMoranowMoviesURL($baseUrl, $date)
    {
        return $baseUrl . "repertuar?month=" . $date;
    }
}
