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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->uuid()->index();
            $table->foreignUuid('users_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->foreignUuid('event_uuid')->constrained('events', 'uuid')->onDelete('cascade');
            $table->foreignUuid('team_leader_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
