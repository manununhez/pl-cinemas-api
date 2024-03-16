<?php

namespace App\Helpers;

use App\Movie;
use App\MoviesInCinema;

class Utils
{
    const EMPTY_TEXT = "";

    public static function isNotNull($item)
    {
        return isset($item) ? $item : self::EMPTY_TEXT;
    }

    public static function isNodeIsNotEmptyText($node)
    {
        return $node->count() > 0 ? $node->text() : self::EMPTY_TEXT;
    }

    public static function isNodeIsNotEmptyAttr($node, $attribute)
    {
        return $node->count() > 0 ? $node->attr($attribute) : self::EMPTY_TEXT;
    }

    public static function insertMovie($cinemaId, $locationId, $linkCinemaMoviePage, Movie $movieToInsert, $date, $language)
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
