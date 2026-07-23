<?php

use Livewire\Component;
use App\Models\LeaveRequest;
use App\Models\SuperappLeaveApprovement;
use App\Services\LeaveRequestSyncService;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\WithPagination;

new class extends Component
{
	use WithPagination;

    public $date;
    public $search = '';
    public $requestId;

    public function mount()
    {
        $this->date = today()->toDateString();

        $this->syncIfNeeded();
    }

    public function updatedDate()
    {
        $this->resetPage();
        $this->syncIfNeeded();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function refresh()
    {
        Cache::forget($this->syncCacheKey());
        $this->syncIfNeeded();
    }

    private function syncIfNeeded(): void
    {
        if (! Cache::add($this->syncCacheKey(), true, now()->addMinutes(5))) {
            return;
        }

        try {
            app(LeaveRequestSyncService::class)->sync($this->date);
        } catch (\Throwable $e) {
            report($e);

            session()->flash('error', 'Gagal mengambil data cuti dari PMS. Data yang tampil mungkin belum terbaru.');
        }
    }

    private function syncCacheKey(): string
    {
        return 'leave-requests:synced:'.$this->date;
    }

    public function recordActualTime($id)
    {
        $request = LeaveRequest::findOrFail($id);

        if ($request->actual_time || $request->is_full_day) {
            return;
        }

        $request->update([
            'actual_time' => Carbon::now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);
    }

    public function recordActualReturn($id)
    {
        $request = LeaveRequest::findOrFail($id);

        if (! $request->actual_time || $request->will_not_return || $request->is_full_day) {
            return;
        }

        $request->update([
            'actual_return' => Carbon::now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);
    }

    public function showRequest($id)
    {
        $this->requestId = $id;

        Flux::modal('view-request')->show();
    }

    #[Computed]
    public function requests()
    {
        return LeaveRequest::query()
            ->where('active', true)
            ->whereDate('date', $this->date)
            ->when($this->search, fn ($query) => $query->whereRaw('LOWER(employee_name) LIKE ?', ['%'.strtolower($this->search).'%']))
            ->orderBy('employee_name')
            ->paginate(10);
    }

    #[Computed]
    public function request()
    {
        if (! $this->requestId) {
            return null;
        }

        return LeaveRequest::find($this->requestId);
    }

    #[Computed]
    public function approvements()
    {
        if (! $this->request || ! $this->request->pms_request_id) {
            return collect();
        }

        try {
            return SuperappLeaveApprovement::query()
                ->with('approver.position')
                ->where('request_id', $this->request->pms_request_id)
                ->where('request_type', 'leave')
                ->where('is_delete', false)
                ->orderBy('step_order')
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Leave Request Tracking</flux:heading>
                    <flux:text class="mt-2">View and track all approved leave requests.</flux:text>
                </div>
                <div>
                    <flux:button icon="arrow-path" wire:click="refresh" wire:target="refresh">
                        Sync Now
                    </flux:button>
                </div>
            </div>

            @if(session('error'))
                <flux:error class="mt-4">{{ session('error') }}</flux:error>
            @endif

            <div class="flex flex-col md:flex-row gap-4 mt-4">
                <flux:input type="date" wire:model.live="date" class="md:w-auto" />
                <flux:input icon="magnifying-glass" placeholder="Search employee..." autocomplete="off" class="flex-1" wire:model.live.debounce.300ms="search" />
            </div>

            <flux:table class="mt-4" :paginate="$this->requests">
                <flux:table.columns>
                    <flux:table.column>Employee</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    <flux:table.column>Leave Type</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Permitted Time</flux:table.column>
                    <flux:table.column>Actual Time</flux:table.column>
                    <flux:table.column>Actual Return</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->requests as $request)
                        <flux:table.row>
                            <flux:table.cell>{{ $request->employee_name }}</flux:table.cell>
                            <flux:table.cell>{{ $request->department }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">
                                    {{ $request->leave_type }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $request->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $request->formatted_time }} </flux:table.cell>
                            <flux:table.cell>
                                @if ($request->is_full_day)
                                    <flux:text class="text-zinc-400">
                                        Not Required
                                    </flux:text>
                                @elseif (! $request->actual_time)
                                    <flux:button
                                        wire:click="recordActualTime({{ $request->id }})"
                                        wire:target="recordActualTime({{ $request->id }})"
                                        >
                                        Record Now
                                    </flux:button>
                                @else
                                    {{ Carbon::parse($request->actual_time)->format('H:i') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($request->is_full_day)
                                    <flux:text class="text-zinc-400">
                                        Not Required
                                    </flux:text>
                                @elseif ($request->will_not_return)
                                    <flux:text class="text-zinc-400">
                                        Will Not Return
                                    </flux:text>
                                @elseif (! $request->actual_time)
                                    -
                                @elseif ($request->actual_return)
                                    {{ Carbon::parse($request->actual_return)->format('H:i') }}
                                @else
                                    <flux:button
                                        wire:click="recordActualReturn({{ $request->id }})"
                                        wire:target="recordActualReturn({{ $request->id }})"
                                        >
                                        Record Now
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    icon="eye"
                                    variant="ghost"
                                    size="sm"
                                    wire:click="showRequest({{ $request->id }})"
                                    wire:target="showRequest({{ $request->id }})"
                                    >
                                    View
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No leave request for this date.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <flux:modal name="view-request" class="md:w-200">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Request Information</flux:heading>
                    </div>
                    @if ($this->request)
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Employee Name</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->employee_name }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Department</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->department ?? '-' }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Leave Type</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->leave_type }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Date</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->formatted_date }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Permitted Time</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->formatted_time }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Will Return</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->will_not_return ? 'No' : 'Yes' }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Reason</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->purpose ?? '-' }}</flux:text>
                            </div>
                            @if ($this->request->destination)
                                <div>
                                    <flux:heading>Destination</flux:heading>
                                    <flux:text class="mt-2" variant="strong">{{ $this->request->destination }}</flux:text>
                                </div>
                            @endif
                        </div>

                        <flux:separator variant="subtle" />

                        <flux:heading size="lg">Approval Details</flux:heading>

                        <div class="space-y-2">
                            @forelse ($this->approvements as $approvement)
                                <div class="flex justify-between items-start bg-gray-400/5 p-4 rounded-xl">
                                    <div variant="strong">
                                        <flux:text>{{ $approvement->approver?->position?->name ?? 'Step '.$approvement->step_order }}</flux:text>
                                        <flux:heading class="my-1">{{ $approvement->approved_by ?? $approvement->approver?->full_name ?? '-' }}</flux:heading>
                                        <flux:text>{{ $approvement->approved_at ? Carbon::parse($approvement->approved_at)->format('d/m/Y H:i') : '-' }}</flux:text>
                                    </div>
                                    <flux:badge
                                        color="{{ match($approvement->approvement_status) {
                                            'approved' => 'green',
                                            'rejected', 'canceled' => 'red',
                                            default => 'zinc',
                                        } }}"
                                        size="sm"
                                        >
                                        {{ ucfirst($approvement->approvement_status) }}
                                    </flux:badge>
                                </div>
                            @empty
                                <flux:text class="text-zinc-400 italic">Belum ada data approval.</flux:text>
                            @endforelse
                        </div>
                    @endif
                </div>
            </flux:modal>
        </div>
    </x-pages::dashboard.layout>
</section>
