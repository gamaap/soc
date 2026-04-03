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
        Schema::create('draft_late_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('department');
            $table->time('actual_arrival');
            $table->unsignedBigInteger('minutes_late')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_late_entries');
    }
};
