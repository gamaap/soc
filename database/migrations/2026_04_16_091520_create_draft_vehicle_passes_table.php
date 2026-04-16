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
        Schema::create('draft_vehicle_passes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('license_plate');
            $table->string('purpose');
            $table->string('destination');
            $table->integer('starting_km')->nullable();
            $table->integer('ending_km')->nullable();
            $table->time('leaving_time')->nullable();
            $table->time('return_time')->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_vehicle_passes');
    }
};
