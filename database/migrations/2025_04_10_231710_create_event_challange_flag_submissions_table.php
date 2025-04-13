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
        Schema::create('event_challange_flag_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_challange_flag_id')->constrained('event_challange_flags', 'id')->onDelete('cascade');
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->text('submission');
            $table->boolean('solved')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('solved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_challange_flag_submissions');
    }
};
