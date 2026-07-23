<?php

namespace App\Console\Commands;

use App\Services\LeaveRequestSyncService;
use Illuminate\Console\Command;

class SyncLeaveRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-requests:sync
        {--date= : Date to sync (Y-m-d), defaults to today}
        {--dry-run : Preview the rows that would be synced without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync approved/validated leave requests from PMS (Superapp) into the local leave_requests table';

    public function handle(LeaveRequestSyncService $service): int
    {
        $date = $this->option('date') ?: now()->toDateString();

        if ($this->option('dry-run')) {
            $rows = $service->preview($date);

            $this->table(
                ['PMS ID', 'Employee', 'Department', 'Leave Type', 'Full Day', 'Permitted Start', 'Permitted End', 'Will Not Return'],
                $rows->map(fn (array $row) => [
                    $row['pms_request_id'],
                    $row['employee_name'],
                    $row['department'],
                    $row['leave_type'],
                    $row['is_full_day'] ? 'Yes' : 'No',
                    $row['permitted_start_time'],
                    $row['permitted_end_time'],
                    $row['will_not_return'] ? 'Yes' : 'No',
                ]),
            );

            $this->info("Dry run: {$rows->count()} row(s) would be synced for {$date}. Nothing was written.");

            return self::SUCCESS;
        }

        $count = $service->sync($date);

        $this->info("Synced {$count} leave request(s) for {$date}.");

        return self::SUCCESS;
    }
}
