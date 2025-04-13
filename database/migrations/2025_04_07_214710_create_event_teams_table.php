<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_uuid');
            $table->string('name');

            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->uuid('leader_uuid');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->foreign('event_uuid')->references('uuid')->on('events')->onDelete('cascade');
            $table->foreign('leader_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->unique(['event_uuid', 'name']);
        });

        Schema::create('event_team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_uuid');
            $table->uuid('user_uuid');
            $table->string('role')->default('member'); // leader, member
            $table->timestamps();

            $table->foreign('team_uuid')->references('id')->on('event_teams')->onDelete('cascade');
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->unique(['team_uuid', 'user_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_members');
        Schema::dropIfExists('event_teams');
    }
};
