<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCinemaLocations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cinema_locations', function (Blueprint $table) {
            $table->id();
            $table->string('cinema_id');
            $table->string('city');
            $table->string('location_id');
            $table->string('name');
            $table->string('coord_latitude')->nullable();
            $table->string('coord_longitude')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cinema_locations');
    }
}
