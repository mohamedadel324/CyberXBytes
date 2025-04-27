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
            $table->string('title');
            $table->longText('description');
            $table->string('difficulty')->only('easy', 'medium', 'hard', 'very_hard');
            $table->integer('bytes');
            $table->integer('firstBloodBytes');
            $table->json('keywords')->nullable();
            $table->string('made_by');
            $table->string('made_by_url')->nullable();
            $table->text('flag')->nullable();   
            $table->enum('flag_type', ['single', 'multiple_all', 'multiple_individual'])->default('single');
            $table->longText('file')->nullable();
            $table->longText('link')->nullable();
            $table->string('made_by')->nullable();
            $table->boolean('available')->default(true);
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
