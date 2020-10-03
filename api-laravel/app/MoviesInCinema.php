<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MoviesInCinema extends Model
{
    const ID = "id";
    const MOVIE_ID = "movie_id";
    const LOCATION_ID = "location_id";
    const CINEMA_ID = "cinema_id";
    const CINEMA_MOVIE_URL = "cinema_movie_url";
    const DAY_TITLE = "date_title";
    const LANGUAGE = "language";
    const TABLE_NAME = 'movies_in_cinema';

    protected $table = 'movies_in_cinema';

    protected $fillable = [ 
        self::MOVIE_ID, self::CINEMA_ID, self::LOCATION_ID, self::CINEMA_MOVIE_URL, self::DAY_TITLE, self::LANGUAGE
    ];

    public function movies(){
        return $this->belongsTo(Movie::class, self::ID, self::MOVIE_ID);
    } 

     public function cinemas(){
      return $this->belongsTo(Cinema::class, self::ID, self::CINEMA_ID);
    }
}
