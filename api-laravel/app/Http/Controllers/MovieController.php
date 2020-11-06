<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Movie;
use App\Cinema;
use App\CinemaLocation;
use App\MoviesInCinema;
use Validator;
use DateTime;
use DateInterval;


class MovieController extends BaseController
{
    public function index()
    {
        //TODO add a request parameter "Date" to filter the list of movies per date
        $movies = Movie::orderBy(Movie::TITLE, 'ASC')->get();;

        $result = collect();

        foreach ($movies as $movie) {
            $cinemas = MoviesInCinema::where(MoviesInCinema::MOVIE_ID, $movie->id)
                // ->select(MoviesInCinema::CINEMA_ID, MoviesInCinema::CINEMA_MOVIE_URL)
                ->get();
            $resultCinemas = collect();

            foreach ($cinemas as $cinema) {

                $locationTmp = CinemaLocation::where(MoviesInCinema::ID, $cinema->location_id)
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


    public function searchMoviesByFilterAttr(Request $request)
    {
        $input = $request->all();
        $locationSearchTerm = "Warszawa"; //default
        $dateSearchTerm = date("Y-m-d"); //default today
        $languageSearchTerm = array();
        $cinemasSearchTerm = array();

        if (isset($input['city']) && $input['city'] !== "") {
            $locationSearchTerm = $input['city'];
        }

        if (isset($input['date']) && $input['date'] !== "") {
            $dateSearchTerm = $input['date'];
        }

        if (isset($input['language']) && sizeof($input['language']) > 0) {
            $languageSearchTerm = $input['language'];
        }

        if (isset($input['cinema']) && sizeof($input['cinema']) > 0) {
            $cinemasSearchTerm = $input['cinema'];
        }


        if (sizeof($cinemasSearchTerm) > 0) {
            $cinemas = Cinema::where(function ($where) use ($cinemasSearchTerm) {
                foreach ($cinemasSearchTerm as $count => $text) {
                    if ($count === 0) {
                        $where->where(Cinema::NAME, 'LIKE', "%{$text}%");
                    } else {
                        $where->orWhere(Cinema::NAME, 'LIKE', "%{$text}%");
                    }
                }
            })->pluck(Cinema::ID);

            $locationIDTmp = CinemaLocation::whereIn(CinemaLocation::CINEMA_ID, $cinemas)
                ->where(CinemaLocation::CITY, "LIKE", $locationSearchTerm)
                ->pluck(CinemaLocation::ID);
        } else {
            $locationIDTmp = CinemaLocation::where(CinemaLocation::CITY, "LIKE", $locationSearchTerm)
                ->pluck(CinemaLocation::ID);
        }

        $movieCinemaTmp = MoviesInCinema::whereIN(MoviesInCinema::LOCATION_ID, $locationIDTmp)
            ->where(MoviesInCinema::DAY_TITLE, $dateSearchTerm)
            ->get();

        $moviesTmp = $movieCinemaTmp->pluck(MoviesInCinema::MOVIE_ID)->unique();

        if (sizeof($languageSearchTerm) > 0) {
            $moviesIDOrdered = Movie::whereIN(Movie::ID, $moviesTmp)
                // ->whereIN(Movie::ORIGINAL_LANG, $languageSearchTerm)
                ->where(function ($where) use ($languageSearchTerm) {
                    foreach ($languageSearchTerm as $count => $text) {
                        if ($count === 0) {
                            $where->where(Movie::ORIGINAL_LANG, 'LIKE', "%{$text}%");
                        } else {
                            $where->orWhere(Movie::ORIGINAL_LANG, 'LIKE', "%{$text}%");
                        }
                    }
                })
                ->orderBy(Movie::TITLE)
                ->pluck(Movie::ID); //Order asc by Title
        } else {
            $moviesIDOrdered = Movie::whereIN(Movie::ID, $moviesTmp)
                ->orderBy(Movie::TITLE)
                ->pluck(Movie::ID); //Order asc by Title
        }

        $resultMoviesID = collect();
        foreach ($moviesIDOrdered as $key => $value) {
            $movie = Movie::where(Movie::ID, $value)->first();

            $locations = MoviesInCinema::where(MoviesInCinema::MOVIE_ID, $movie->id)
                ->whereIn(MoviesInCinema::LOCATION_ID, $locationIDTmp)
                ->where(MoviesInCinema::DAY_TITLE, $dateSearchTerm)
                ->get();

            $resultCinemas = collect();
            foreach ($locations as $location) {
                $locationTmp = CinemaLocation::where(MoviesInCinema::ID, $location->location_id)
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
                    MoviesInCinema::LANGUAGE => $location[MoviesInCinema::LANGUAGE]
                ]);
            }

            //$movie[MoviesInCinema::DAY_TITLE] = $dateSearchTerm;
            //$movie[CinemaLocation::CITY] = $locationSearchTerm;
            $resultMoviesID->push([
                Movie::ID => $movie[Movie::ID],
                Movie::TITLE => $movie[Movie::TITLE],
                Movie::DESCRIPTION => $movie[Movie::DESCRIPTION],
                Movie::ORIGINAL_LANG => $movie[Movie::ORIGINAL_LANG],
                Movie::DURATION => $movie[Movie::DURATION],
                Movie::CLASSIFICATION => $movie[Movie::CLASSIFICATION],
                Movie::GENRE => $movie[Movie::GENRE],
                Movie::YEAR => $movie[Movie::YEAR],
                MoviesInCinema::DAY_TITLE => $dateSearchTerm,
                CinemaLocation::CITY => $locationSearchTerm,
                Movie::TRAILER => $movie[Movie::TRAILER],
                Movie::POSTER => $movie[Movie::POSTER],
                "cinemas" => $resultCinemas
            ]);
        }

        return $this->sendResponse($resultMoviesID, 'movies retrieved successfully.');
    }

    public function getAttributes(Request $request)
    {
        $filteredMoviesGroupByDate = $this->getMoviesDateTitleAvailabilityByFilterAttr($request);

        $d = collect();
        $count = 0;
        for ($x = BackupController::DAYS_START_FROM_TODAY; $x <= BackupController::DAYS_IN_ADVANCE; $x++) {
            $date = new DateTime(BackupController::TIMEZONE);
            $date->add(new DateInterval('P' . $x . 'D')); //('P30D'));
            $date = $date->format('Y-m-d'); //date("d-m-Y");//now
            
            //**** version 1
            // $total = MoviesInCinema::select(DB::raw('count(*) as total'))
            //                         ->whereIN(MoviesInCinema::MOVIE_ID, $moviesIDOrdered)
            //                         ->whereIN(MoviesInCinema::LOCATION_ID, $locationIDTmp)
            //                         ->where(MoviesInCinema::DAY_TITLE, $date)
            //                         ->groupBy(MoviesInCinema::DAY_TITLE)
            //                         ->first();

            // $d->push(["date" => $date, "total" => $total['total']]);

            //**** version 2
            if($date === $filteredMoviesGroupByDate->get($count)['date_title']){
                $d->push(["date" => $date, "movies_available" => true]);
                $count++;
            } else {
                $d->push(["date" => $date, "movies_available" => false]);
            }  
        }

        $city = new CinemaLocation();
        $cinema = new Cinema();
        $result = [
                "cinemas" => $cinema->getCinemas(),
                "cities" => $city->getCinemaCities(),
                "days" => $d,
                "languages" => BackupController::LANGUAGES
                ];

        return $this->sendResponse($result, 'movies retrieved successfully.');
    }

    private function getMoviesDateTitleAvailabilityByFilterAttr(Request $request){
        $input = $request->all();
        $locationSearchTerm = "Warszawa"; //default
        $languageSearchTerm = array();
        $cinemasSearchTerm = array();

        if (isset($input['city']) && $input['city'] !== "") {
            $locationSearchTerm = $input['city'];
        }

        if (isset($input['language']) && sizeof($input['language']) > 0) {
            $languageSearchTerm = $input['language'];
        }

        if (isset($input['cinema']) && sizeof($input['cinema']) > 0) {
            $cinemasSearchTerm = $input['cinema'];
        }


        if (sizeof($cinemasSearchTerm) > 0) {
            $cinemas = Cinema::where(function ($where) use ($cinemasSearchTerm) {
                foreach ($cinemasSearchTerm as $count => $text) {
                    if ($count === 0) {
                        $where->where(Cinema::NAME, 'LIKE', "%{$text}%");
                    } else {
                        $where->orWhere(Cinema::NAME, 'LIKE', "%{$text}%");
                    }
                }
            })->pluck(Cinema::ID);

            $locationIDTmp = CinemaLocation::whereIn(CinemaLocation::CINEMA_ID, $cinemas)
                ->where(CinemaLocation::CITY, "LIKE", $locationSearchTerm)
                ->pluck(CinemaLocation::ID);
        } else {
            $locationIDTmp = CinemaLocation::where(CinemaLocation::CITY, "LIKE", $locationSearchTerm)
                ->pluck(CinemaLocation::ID);
        }

        $movieCinemaTmp = MoviesInCinema::whereIN(MoviesInCinema::LOCATION_ID, $locationIDTmp)
                                        ->get();

        $moviesTmp = $movieCinemaTmp->pluck(MoviesInCinema::MOVIE_ID)->unique();

        if (sizeof($languageSearchTerm) > 0) {
            $moviesIDOrdered = Movie::whereIN(Movie::ID, $moviesTmp)
                                    ->where(function ($where) use ($languageSearchTerm) {
                                        foreach ($languageSearchTerm as $count => $text) {
                                            if ($count === 0) {
                                                $where->where(Movie::ORIGINAL_LANG, 'LIKE', "%{$text}%");
                                            } else {
                                                $where->orWhere(Movie::ORIGINAL_LANG, 'LIKE', "%{$text}%");
                                            }
                                        }
                                    })
                                    ->orderBy(Movie::TITLE)
                                    ->pluck(Movie::ID); //Order asc by Title
        } else {
            $moviesIDOrdered = Movie::whereIN(Movie::ID, $moviesTmp)
                                    ->orderBy(Movie::TITLE)
                                    ->pluck(Movie::ID); //Order asc by Title
        }

        $filteredMoviesGroupByDate = MoviesInCinema::select(MoviesInCinema::DAY_TITLE) //DB::raw('count(*) > 0 as enable'), 
                                ->whereIN(MoviesInCinema::MOVIE_ID, $moviesIDOrdered)
                                ->whereIN(MoviesInCinema::LOCATION_ID, $locationIDTmp)
                                ->groupBy(MoviesInCinema::DAY_TITLE)
                                ->get();

        return $filteredMoviesGroupByDate;
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


        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }


        $movie = Movie::create($input);

        if (is_null($movie))
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

        if (!$movie)
            return $this->sendError('Movie with id = ' . $id . ' not found.', 400);


        $deleted = $movie->delete();

        if ($deleted)
            return $this->sendResponse($movie->toArray(), 'Movie deleted successfully.');
        else
            return $this->sendError('Movie could not be deleted', 500);
    }
}
