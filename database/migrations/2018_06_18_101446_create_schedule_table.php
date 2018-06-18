<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScheduleTable extends Migration
{

    public function up()
    {
        Schema::create('schedule', function(Blueprint $table) {
            $table->increments('id');
            $table->string('chat_id');

            $table->foreign('chat_id')
                ->references('chat_id')
                ->on('users')
                ->onDelete('cascade');

            $table->dateTime('time');
            // Constraints declaration

        });
    }

    public function down()
    {
        Schema::drop('schedule');
    }
}