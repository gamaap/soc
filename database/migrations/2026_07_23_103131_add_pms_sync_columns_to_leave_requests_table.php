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
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('pms_request_id')->nullable()->after('id');
            $table->unsignedBigInteger('pms_employee_id')->nullable()->after('pms_request_id');
            $table->unsignedBigInteger('request_sub_type_id')->nullable()->after('leave_type');
            $table->boolean('will_not_return')->default(false)->after('permitted_end_time');
            $table->boolean('is_full_day')->default(false)->after('will_not_return');
            $table->string('destination')->nullable()->after('purpose');
            $table->timestamp('synced_at')->nullable();

            $table->unique(['pms_request_id', 'date']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('department')->nullable()->change();
            $table->time('permitted_start_time')->nullable()->change();
        });

        // `leave_requests` is empty in every environment (feature not yet implemented),
        // so recreate the column instead of casting varchar -> time.
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('actual_return');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->time('actual_return')->nullable()->after('actual_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('actual_return');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('actual_return')->nullable()->after('actual_time');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->time('permitted_start_time')->nullable(false)->change();
            $table->string('department')->nullable(false)->change();
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropUnique(['pms_request_id', 'date']);
            $table->dropColumn([
                'pms_request_id',
                'pms_employee_id',
                'request_sub_type_id',
                'will_not_return',
                'is_full_day',
                'destination',
                'synced_at',
            ]);
        });
    }
};
