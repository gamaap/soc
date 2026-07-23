<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\SuperappLeaveRequest;
use Illuminate\Support\Collection;

class LeaveRequestSyncService
{
    /**
     * Pull approved/validated leave requests from PMS for a given date and
     * upsert them into the local `leave_requests` table. Requests that no
     * longer qualify (cancelled, rejected) are deactivated unless security
     * already recorded an actual exit time for them.
     */
    public function sync(string $date): int
    {
        $rows = $this->mappedRows($date);

        foreach ($rows as $attributes) {
            $leaveRequest = LeaveRequest::firstOrNew([
                'pms_request_id' => $attributes['pms_request_id'],
                'date' => $date,
            ]);

            $leaveRequest->fill($attributes);
            $leaveRequest->active = true;
            $leaveRequest->synced_at = now();
            $leaveRequest->save();
        }

        LeaveRequest::query()
            ->whereDate('date', $date)
            ->whereNotIn('pms_request_id', $rows->pluck('pms_request_id'))
            ->whereNull('actual_time')
            ->update(['active' => false]);

        return $rows->count();
    }

    /**
     * Preview what `sync()` would write for a given date, without touching
     * the local database. Used by `leave-requests:sync --dry-run`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function preview(string $date): Collection
    {
        return $this->mappedRows($date);
    }

    /**
     * Map a raw PMS request row into `leave_requests` attributes. Kept as a
     * pure function (no DB access) so the date/time mapping rules can be
     * unit tested without touching either database.
     *
     * @param  array{
     *     pms_request_id: int,
     *     pms_employee_id: int,
     *     employee_name: string,
     *     department: ?string,
     *     leave_type: string,
     *     request_sub_type_id: int,
     *     period_start_time: ?string,
     *     period_end_time: ?string,
     *     leaving_time: ?string,
     *     will_not_return: ?string,
     *     reason: ?string,
     *     destination: ?string,
     * }  $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row, string $date): array
    {
        $permittedStartTime = $row['period_start_time'] ?? $row['leaving_time'] ?? null;
        $permittedEndTime = $row['period_end_time'] ?? null;
        $isFullDay = is_null($permittedStartTime) && is_null($permittedEndTime);
        $isPeriodBased = ! is_null($permittedStartTime) && ! is_null($permittedEndTime);

        $attributes = [
            'pms_request_id' => $row['pms_request_id'],
            'pms_employee_id' => $row['pms_employee_id'],
            'employee_name' => $row['employee_name'],
            'department' => $row['department'],
            'leave_type' => $row['leave_type'],
            'request_sub_type_id' => $row['request_sub_type_id'],
            'date' => $date,
            'permitted_start_time' => $permittedStartTime,
            'permitted_end_time' => $permittedEndTime,
            'is_full_day' => $isFullDay,
            'will_not_return' => ($row['will_not_return'] ?? '0') === '1',
            'purpose' => $row['reason'] ?? null,
            'destination' => $row['destination'] ?? null,
        ];

        // 2-Hour/Half Day leave (period-based, not Annual/Sick/full-day and
        // not the leaving-time-only travel type) that starts at or after
        // 07:55 (shift start) means the employee was already inside the
        // facility that morning: their exit is taken from the permit itself
        // instead of a manual security scan, leaving only the return to record.
        if ($isPeriodBased && $permittedStartTime >= '07:55:00') {
            $attributes['actual_time'] = $permittedStartTime;
        }

        return $attributes;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mappedRows(string $date): Collection
    {
        $requests = SuperappLeaveRequest::query()
            ->with(['employee.department', 'subType'])
            ->where('is_delete', false)
            ->whereIn('final_approvement', ['approved', 'validated'])
            ->whereHas('subType', fn ($query) => $query->where('request_type_id', 1))
            ->whereHas('employee', fn ($query) => $query->where('plant_id', 1)->where('is_delete', false))
            ->where(function ($query) use ($date) {
                $query->whereDate('date', $date)
                    ->orWhere(fn ($sub) => $sub->whereDate('start_date', '<=', $date)
                        ->whereDate('end_date', '>=', $date));
            })
            ->get();

        return $requests->map(fn ($request) => $this->mapRow([
            'pms_request_id' => $request->id,
            'pms_employee_id' => $request->requested_by,
            'employee_name' => $request->employee?->full_name ?? '-',
            'department' => $request->employee?->department?->name,
            'leave_type' => $request->subType?->display_name ?? '-',
            'request_sub_type_id' => $request->request_sub_type_id,
            'period_start_time' => $request->period_start_time,
            'period_end_time' => $request->period_end_time,
            'leaving_time' => $request->leaving_time,
            'will_not_return' => $request->will_not_return,
            'reason' => $request->reason,
            'destination' => $request->destination,
        ], $date));
    }
}
