<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MoviesInCinema extends Model
{
    protected $table = 'movies_in_cinema';

    protected $fillable = ['movie_id','cinema_id'];

    public function movies(){
        return $this->belongsTo(Movie::class, 'id', 'movie_id');
    } 

     public function cinemas(){
      return $this->belongsTo(Cinema::class, 'id', 'cinema_id');
    }
}
