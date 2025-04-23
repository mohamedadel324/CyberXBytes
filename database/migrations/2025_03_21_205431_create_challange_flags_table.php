<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('challange_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challange_id')->constrained()->onDelete('cascade');
            $table->string('flag');
            $table->integer('bytes')->default(0);
            $table->integer('firstBloodBytes')->default(0);
            $table->string('name')->nullable();
            $table->string('ar_name')->nullable();

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('challange_flags');
    }
}; 