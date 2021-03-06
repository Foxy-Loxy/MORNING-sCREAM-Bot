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

            $table->string('time')->nullable()->default('07:00');
            $table->string('utc')->nullable()->default('+00:00');
            $table->string('utc_time')->nullable()->default('07:00');
            // Constraints declaration
			$table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('schedule');
    }
}
