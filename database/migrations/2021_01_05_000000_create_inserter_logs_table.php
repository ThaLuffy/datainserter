<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInserterLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inserter_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('type');
            $table->string('receiving_model');
            $table->string('inserting_model');
            $table->string('model_id');
            $table->string('job_id');
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
        Schema::dropIfExists('index_logs');
    }
}
