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
        Schema::create('draft_delivery_items_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('draft_delivery_entries')->onDelete('cascade');
            $table->string('item_name');
            $table->integer('quantity')->default(0);
            $table->string('uom');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_delivery_items_entries');
    }
};
