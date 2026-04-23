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
        Schema::create('other_passes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('department');
            $table->string('license_plate');
            $table->string('purpose')->nullable();
            $table->time('leaving_time');
            $table->time('entry_time')->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_passes');
    }
};
