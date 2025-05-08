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
            $table->uuid()->index();
            $table->string('title');
            $table->text('description');
            $table->longText('image');
            $table->longText('background_image');
            $table->boolean('is_private')->default(false);

            // Registration period
            $table->dateTime('registration_start_date');
            $table->dateTime('registration_end_date');

            // Team formation period
            $table->dateTime('team_formation_start_date');
            $table->dateTime('team_formation_end_date');

            // Event visibility and actual event dates
            $table->dateTime('start_date');
            $table->dateTime('end_date');

            // Team requirements
            $table->boolean('requires_team')->default(true);
            $table->integer('team_minimum_members');
            $table->integer('team_maximum_members');
            //freeze
            $table->boolean('freeze')->default(false);
            $table->timestamp('freeze_time')->nullable();


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
