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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('employee_name');
            $table->string('department');
            $table->string('leave_type');
            $table->text('purpose')->nullable();
            $table->date('date');
            $table->time('permitted_start_time');
            $table->time('permitted_end_time')->nullable();
            $table->time('actual_time')->nullable();
            $table->string('actual_return')->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
