<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('title');
            $table->string('description');
            $table->longText('image');

            $table->dateTime('visible_start_date');
            $table->dateTime('start_date');
            $table->dateTime('end_date');

            $table->integer('team_minimum_members');
            $table->integer('team_maximum_members');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
