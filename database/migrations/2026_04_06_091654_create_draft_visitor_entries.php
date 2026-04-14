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
        Schema::create('draft_visitor_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('visiting');
            $table->string('license_plate')->nullable();
<<<<<<< HEAD
=======
            $table->integer('card_number')->nullable();
>>>>>>> 2085cb4241a99dd50846ea10f3e25378cb887386
            $table->text('purpose');
            $table->time('entry_time');
            $table->time('exit_time')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_visitor_entries');
    }
};
