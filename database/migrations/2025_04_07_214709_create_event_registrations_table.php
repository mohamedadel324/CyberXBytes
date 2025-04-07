<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_uuid');
            $table->uuid('user_uuid');
            $table->string('status')->default('registered'); // registered, team_assigned
            $table->timestamps();

            $table->foreign('event_uuid')->references('uuid')->on('events')->onDelete('cascade');
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->unique(['event_uuid', 'user_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
