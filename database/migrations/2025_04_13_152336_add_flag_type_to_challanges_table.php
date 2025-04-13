<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('challanges', function (Blueprint $table) {
            $table->string('flag_type')->default('single')->after('description');
        });
    }

    public function down()
    {
        Schema::table('challanges', function (Blueprint $table) {
            $table->dropColumn('flag_type');
        });
    }
}; 