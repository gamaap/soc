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
        Schema::create('draft_employee_pass_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('department');
            $table->string('license_plate');
            $table->time('entry_time');
            $table->time('leaving_time')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_employee_pass_entries');
    }
};
