<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CinemaLocation extends Model
{
    const ID = 'id';
    const LOCATION_ID = 'location_id';
    const NAME = 'name';
    const CINEMA_ID = 'cinema_id';
    const CITY = 'city';
    const COORD_LATITUDE = 'coord_latitude';
    const COORD_LONGITUDE = 'coord_longitude';

    protected $table = 'cinema_locations';

    // // If you wish to use a non-incrementing or a non-numeric primary key you must set the public $incrementing property on your model to false
    // public $incrementing = false;
    
    protected $fillable = [
        self::NAME, self::CINEMA_ID, self::CITY, self::LOCATION_ID, self::COORD_LATITUDE, self::COORD_LONGITUDE
    ];

    function getCinemaCities(){
        // return $this->distinct()->orderBy(self::CITY)->groupBy(self::CITY, self::LOCATION_ID)->get([self::CITY, self::LOCATION_ID]);

        return $this->distinct()->orderBy(self::CITY)->groupBy(self::CITY)->get(self::CITY)->pluck(self::CITY);
    }
}
