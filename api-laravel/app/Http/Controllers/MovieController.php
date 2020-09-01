<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Movie;
use App\Cinema;
use App\CinemaLocation;
use App\MoviesInCinema;
use Validator;


class MovieController extends BaseController
{
    public function index(){
        //TODO add a request parameter "Date" to filter the list of movies per date
        $movies = Movie::orderBy(Movie::TITLE, 'ASC')->get();;
        
        $result = collect();

        foreach($movies as $movie){
            $cinemas = MoviesInCinema::
                            where(MoviesInCinema::MOVIE_ID, "=", $movie->id)
                            // ->select(MoviesInCinema::CINEMA_ID, MoviesInCinema::CINEMA_MOVIE_URL)
                            ->get();
            $resultCinemas = collect();
            
            foreach($cinemas as $cinema){
                
                $locationTmp = CinemaLocation::where(MoviesInCinema::ID, "=", $cinema->location_id)
                                            ->first();

                
                $cinemaTmp = Cinema::find($cinema->cinema_id);

                $resultCinemas->push([
                    CinemaLocation::CINEMA_ID => $locationTmp[CinemaLocation::CINEMA_ID],
                    CinemaLocation::LOCATION_ID => $locationTmp[CinemaLocation::LOCATION_ID],
                    CinemaLocation::NAME => $locationTmp[CinemaLocation::NAME],
                    CinemaLocation::COORD_LATITUDE => $locationTmp[CinemaLocation::COORD_LATITUDE],
                    CinemaLocation::COORD_LONGITUDE => $locationTmp[CinemaLocation::COORD_LONGITUDE],
                    Cinema::LOGO => $cinemaTmp[Cinema::LOGO],
                    MoviesInCinema::CINEMA_MOVIE_URL => $cinema[MoviesInCinema::CINEMA_MOVIE_URL],
                ]);
            }
            
            $result->push([
                "movie" => $movie, 
                "cinemas" => $resultCinemas
            ]);
        }
        // $movies = MoviesInCinema::all()->groupBy('movie_id');
        // $movies = MoviesInCinema::with('movies')->with('cinemas')->get();

        return $this->sendResponse($result, 'movies retrieved successfully.');
    }


    public function getMoviesByLocation(Request $request){
        
        $locationSearchTerm = isset($request->city) ? $request->city : "Warszawa";

        $locationIDTmp = CinemaLocation::where(CinemaLocation::CITY, "LIKE", $locationSearchTerm)
                                        ->pluck(CinemaLocation::ID);
        
        $movieCinemaTmp = MoviesInCinema::whereIN(MoviesInCinema::LOCATION_ID, $locationIDTmp)
                                        ->get();
        
        $moviesTmp = $movieCinemaTmp->pluck(MoviesInCinema::MOVIE_ID)->unique();
        
        $resultMoviesID = collect();
        foreach($moviesTmp as $key => $value){
            $movie = Movie::where(Movie::ID, "=", $value)->first();
            
            $locations = MoviesInCinema::where(MoviesInCinema::MOVIE_ID, "=", $movie->id)
                                        ->whereIn(MoviesInCinema::LOCATION_ID, $locationIDTmp)
                                        ->get();
            
            $resultCinemas = collect();
            foreach($locations as $location){
                $locationTmp = CinemaLocation::where(MoviesInCinema::ID, "=", $location->location_id)
                                                ->first();
               
                $cinemaTmp = Cinema::find($location->cinema_id);
                $resultCinemas->push([
                    CinemaLocation::CINEMA_ID => $locationTmp[CinemaLocation::CINEMA_ID],
                    CinemaLocation::LOCATION_ID => $locationTmp[CinemaLocation::LOCATION_ID],
                    CinemaLocation::NAME => $locationTmp[CinemaLocation::NAME],
                    CinemaLocation::COORD_LATITUDE => $locationTmp[CinemaLocation::COORD_LATITUDE],
                    CinemaLocation::COORD_LONGITUDE => $locationTmp[CinemaLocation::COORD_LONGITUDE],
                    Cinema::LOGO => $cinemaTmp[Cinema::LOGO],
                    MoviesInCinema::CINEMA_MOVIE_URL => $location[MoviesInCinema::CINEMA_MOVIE_URL],
                ]);
            }

            $resultMoviesID->push([
                "movie" => $movie,
                "cinemas" => $resultCinemas
            ]);
        }
        
        return $this->sendResponse($resultMoviesID, 'movies retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->all();


        $validator = Validator::make($input, [
            'title' => 'required|string',
            'description' => 'required|string',
            'trailer_url' => 'required|string',
            'poster_url' => 'required|string',
        ]);


        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 400);       
        }


        $movie = Movie::create($input);

        if(is_null($movie))
            return $this->sendError('Movie could not be created', 500);
        else
            return $this->sendResponse($movie->toArray(), 'Movie created successfully.');
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $movie = Movie::find($id);

    	if(!$movie)
    		return $this->sendError('Movie with id = '.$id.' not found.', 400);


    	$deleted = $movie->delete();

    	if($deleted)
    		return $this->sendResponse($movie->toArray(), 'Movie deleted successfully.');
    	else
    		return $this->sendError('Movie could not be deleted', 500);
    }
}