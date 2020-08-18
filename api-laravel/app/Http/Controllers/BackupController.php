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

        $response = Http::get('https://multikino.pl/api/sitecore/WhatsOn/WhatsOnV2Alphabetic?cinemaId=43');
        

        $result = collect();

        foreach ($response["WhatsOnAlphabeticFilms"] as $key => $item) {
            $this->_insertMovie($cinema, $item['Title'], $item['Synopsis'], $item['TrailerUrl'], $item['Poster']);
            // $result->push([ 
            //     "title" => $item['Title'],
            //     "short_description" => $item['ShortSynopsis'],
            //     "description" => $item['Synopsis'],
            //     "description_url" => $item['FilmUrl'],
            //     "trailer_url" => $item['TrailerUrl'],
            //     "image_url" => $item['Poster'],
            //     "language" => $this->_getLanguage($item['WhatsOnAlphabeticCinemas'][0])
            // ]);   
        }
        return true;
    }

    function _cinemacity(){
        $cinema = Cinema::firstWhere('name', '=', "Cinema City");
        $response = Http::get('https://www.cinema-city.pl/pl/data-api-service/v1/quickbook/10103/film-events/in-cinema/1070/at-date/2020-08-18?attr=&lang=pl_PL');

        $result = collect();

        foreach ($response["body"]["films"] as $key => $item) {
            $this->_insertMovie($cinema, $item['name'], $item['link'], $item['videoLink'], $item['posterLink']);

            // $result->push([ 
            //     "title" => $item['name'],
            //     "description_url" => $item['link'],
            //     "trailer_url" => $item['videoLink'],
            //     "image_url" => $item['posterLink'],
            //     "language" => $item['attributeIds'] 
            // ]);   
        }
        return true;
    }

    function _kinoMoranow(){
        $client = new Client();

        $crawler = $client->request('GET', 'https://kinomuranow.pl/repertuar?month=2020-08');
        $cinema = Cinema::firstWhere('name', '=', "Kino Muranow");

        $movie = $crawler
            ->filter("div.rep-film-desc-wrapper")
            // ->reduce(function ($node) use ($temp) {
            //     return !in_array($node->filter("div.rep-film-desc-mobile a")->attr('href'), $temp);
            // })
            ->each(function ($node) use ($cinema) {
                $title = $node->filter("div.rep-film-desc-mobile div.rep-film-show-title")->text();
                $description = $node->filter("div.rep-film-desc-mobile a")->attr('href');
                $poster = $node->filter("div.rep-film-show-hover-wrapper img")->attr('src');
                $trailer = $node->filter("div.rep-film-show-hover-wrapper img")->attr('src');

                $this->_insertMovie($cinema, $title, $description, $trailer, $poster);

                // $result = [
                //     "title" => $node->filter("div.rep-film-desc-mobile div.rep-film-show-title")->text(),
                //     "description_url" =>  $node->filter("div.rep-film-desc-mobile a")->attr('href'),
                //     "date" => $node->filter("div.rep-film-desc-mobile div.rep-film-show-date")->text(),
                //     "image_url" => $node->filter("div.rep-film-show-hover-wrapper img")->attr('src')
                // ];

                // array_push($temp, $node->filter("div.rep-film-desc-mobile a")->attr('href'));

                
                //return $result;
        });

        return true;
    }

    function _insertMovie($cinema, $title, $description, $trailer, $poster){
        $movie = Movie::firstWhere('title', '=', $title);

        if(!$movie){ //if the movie does not exist
            echo("Movie does not exist");
            //first create the new movie
            $newMovie = Movie::create([
                "title" => $title,
                "description" => isset($description) ? $description : "",
                "trailer_url" => isset($trailer) ? $trailer : "",
                "poster_url" => isset($poster) ? $poster : ""
            ]);

            //echo($newMovie);

            // //then associate the movie with the cinema
            if(!is_null($newMovie)){
                $movieInserted = Movie::where("title","=", $title)->first();
                // echo(response()->json($movieInserted['id']));
                echo("Insert moviesInCinema: ".$movieInserted->id." ".$cinema->id);
                $moviesInCinema = MoviesInCinema::create([
                    "movie_id" => $movieInserted->id,
                    "cinema_id" => $cinema->id
                ]);
            }

        } else { //if the movie already exists
            echo("Movie does exist");
            //we find if the cinema is already associated with the movie
            $moviesInCinema = MoviesInCinema::where("movie_id","=", $movie->id)->where("cinema_id","=", $cinema->id)->first();
        
            if(!$moviesInCinema){   //if the cinema if not associated with the movie, we create it
                echo("Insert moviesInCinema: ".$movie->id." ".$cinema->id);
                $moviesInCinema = MoviesInCinema::create([
                    "movie_id" => $movie->id,
                    "cinema_id" => $cinema->id
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