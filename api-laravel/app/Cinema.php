<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cinema extends Model
{
    const ID = 'id';
    const NAME = 'name';
    const WEBSITE = 'website';
    const LOGO = 'logo_url';

    protected $table = 'cinemas';

    // // If you wish to use a non-incrementing or a non-numeric primary key you must set the public $incrementing property on your model to false
    // public $incrementing = false;
    
    protected $fillable = [
        self::NAME, self::WEBSITE, self::LOGO
    ];
}
