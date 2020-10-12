<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMoviesInCinemaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movies_in_cinema', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('movie_id')->unsigned();
            $table->bigInteger('cinema_id')->unsigned();
            $table->bigInteger('location_id')->unsigned();
            $table->string('language');
            $table->string('cinema_movie_url');
            $table->string('date_title');
            $table->timestamps();

            $table->unique(['movie_id', 'cinema_id', 'location_id', 'date_title'], 'un_movies_cinema');

            $table->foreign('movie_id')->references('id')->on('movies')->onDelete('cascade');
            $table->foreign('cinema_id')->references('id')->on('cinemas')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('cinema_locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('movies_in_cinema');
    }
}
