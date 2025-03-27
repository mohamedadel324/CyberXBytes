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
        Schema::create('challanges', function (Blueprint $table) {
            $table->id();
            $table->uuid()->index();
            $table->foreignUuid('lab_category_uuid')->constrained('lab_categories', 'uuid')->onDelete('cascade');
            $table->foreignUuid('category_uuid')->constrained('challange_categories', 'uuid')->onDelete('cascade');
            $table->json('key_words');
            $table->string('title');
            $table->longText('description');
            $table->string('image');
            $table->string('difficulty')->only('easy', 'medium', 'hard', 'very_hard');
            $table->integer('bytes');
            $table->integer('firstBloodBytes');
            $table->text('flag');
            $table->longText('file')->nullable();
            $table->longText('link')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challanges');
    }
};
