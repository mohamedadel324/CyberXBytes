<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_team_join_secrets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_uuid');
            $table->string('secret', 16);
            $table->boolean('used')->default(false);
            $table->uuid('used_by_uuid')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('team_uuid')->references('id')->on('event_teams')->onDelete('cascade');
            $table->foreign('used_by_uuid')->references('uuid')->on('users')->onDelete('set null');
            $table->unique('secret');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_join_secrets');
    }
};
