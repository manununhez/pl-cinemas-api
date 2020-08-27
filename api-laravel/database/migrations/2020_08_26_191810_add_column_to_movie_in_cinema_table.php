<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToMovieInCinemaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('movies_in_cinema', function (Blueprint $table) {
            $table->string('cinema_movie_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('movies_in_cinema', function (Blueprint $table) {
            $table->dropColumn('cinema_movie_url');
        });
    }
}
