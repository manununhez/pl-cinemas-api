<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Movie;
use App\Cinema;
use App\MoviesInCinema;
use Validator;


class MovieController extends BaseController
{
    public function index(){
        $movies = Movie::all();
        
        $result = collect();

        foreach($movies as $movie){
            $cinemas = MoviesInCinema::
                            where("movie_id", "=", $movie->id)
                            ->select('cinema_id','cinema_movie_url')
                            ->get();
            $resultCinemas = collect();
            
            foreach($cinemas as $cinema){
                $cinemaTmp = Cinema::find($cinema->cinema_id);

                $resultCinemas->push([
                    "id" => $cinemaTmp['id'],
                    "name" => $cinemaTmp['name'],
                    "website" => $cinemaTmp['website'],
                    "logo_url" => $cinemaTmp['logo_url'],
                    "cinema_movie_url" => $cinema->cinema_movie_url,

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