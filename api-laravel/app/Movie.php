<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    const ID = 'id';
    const TITLE = 'title';
    const DESCRIPTION = 'description';
    const ORIGINAL_LANG = 'original_lang';
    const DURATION = 'duration';
    const GENRE = 'genre';
    const CLASSIFICATION = 'classification';
    const YEAR = 'release_year';
    const TRAILER = 'trailer_url';
    const POSTER = 'poster_url';

    protected $table = 'movies';

    // // If you wish to use a non-incrementing or a non-numeric primary key you must set the public $incrementing property on your model to false
    // public $incrementing = false;
    
    protected $fillable = [
        self::TITLE, self::DESCRIPTION, self::ORIGINAL_LANG, self::DURATION, self::GENRE, self::CLASSIFICATION, self::YEAR, self::TRAILER, self::POSTER
    ];

    public function cinemas(){
        return $this->hasMany(Cinema::class, MoviesInCinema::MOVIE_ID, self::ID)
                    ->groupBy(MoviesInCinema::MOVIE_ID, 'created_at')
                    ->orderBy('created_at','desc');
    }
}
