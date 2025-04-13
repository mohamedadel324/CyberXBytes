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
        Schema::create('user_challanges', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->foreignUuid('category_uuid')->constrained('challange_categories', 'uuid')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->string('difficulty');
            $table->text('flag');
            $table->longText('challange_file');
            $table->longText('answer_file');
            $table->text('notes');
            $table->enum('status', ['pending', 'declined', 'under_review', 'approved'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challanges');
    }
};
