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
        Schema::create('key_borrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_key_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('facility_key_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_department');
            $table->timestamp('borrowed_at');
            $table->string('returned_name')->nullable();
            $table->string('returned_department')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('borrow_recorded_by')->nullable();
            $table->foreignId('return_recorded_by')->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('key_borrowings');
    }
};
