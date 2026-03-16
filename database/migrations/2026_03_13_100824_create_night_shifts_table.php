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
        Schema::create('night_shifts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('department');
            $table->time('check_in_time');
            $table->time('check_out_time')->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('night_shifts');
    }
};