<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    protected $table = 'movies';

    // If you wish to use a non-incrementing or a non-numeric primary key you must set the public $incrementing property on your model to false
    public $incrementing = false;
    
    protected $fillable = [
        'title','description','duration','classification','release_year','trailer_url', 'poster_url'
    ];

    public function cinemas(){
        return $this->hasMany(Cinema::class, "movie_id", "id")->groupBy('movie_id', 'created_at')->orderBy('created_at','desc');
    }
}
