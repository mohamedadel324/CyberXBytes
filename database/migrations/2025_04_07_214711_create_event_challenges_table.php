<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_challanges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_uuid')->constrained('events', 'uuid')->onDelete('cascade');
            $table->foreignUuid('category_uuid')->constrained('challange_categories', 'uuid')->onDelete('cascade');
            $table->string('title');
            $table->longText('description');
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'very_hard']);
            $table->integer('bytes')->nullable();
            $table->integer('firstBloodBytes')->nullable();
            $table->text('flag')->nullable();
            $table->json('keywords')->nullable();
            $table->enum('flag_type', ['single', 'multiple_all', 'multiple_individual'])->default('single');
            $table->longText('file')->nullable();
            $table->longText('link')->nullable();
            $table->string('made_by');
            $table->string('made_by_url')->nullable();
            $table->timestamps();
        });

        Schema::create('event_challange_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_challange_id')->constrained('event_challanges', 'id')->onDelete('cascade');
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->text('submission');
            $table->boolean('solved')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('solved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_challange_submissions');
        Schema::dropIfExists('event_challanges');
    }
};
