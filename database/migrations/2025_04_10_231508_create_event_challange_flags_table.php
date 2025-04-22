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
        Schema::create('event_challange_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_challange_id')->constrained('event_challanges', 'id')->onDelete('cascade');
            $table->string('flag');
            $table->integer('bytes')->nullable();
            $table->integer('firstBloodBytes')->nullable();
            $table->string('name')->nullable();
            $table->string('ar_name')->nullable();
            $table->text('description')->nullable();
            $table->integer('order')->nullable()->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_challange_flags');
    }
};
