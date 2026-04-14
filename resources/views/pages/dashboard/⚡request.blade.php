<?php

use Livewire\Component;
use App\Models\LeaveRequest;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Livewire\WithPagination;

new class extends Component
{
	use WithPagination;
	
    public $date;
    public $permitted_start_time;
    public $permitted_end_time;
    public $requestId;

    public function recordActualTime($id)
    {
        $request = LeaveRequest::findOrFail($id);

        $request->update([
            'actual_time' => Carbon::now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);
    }

    public function recordActualReturn($id)
    {
        $request = LeaveRequest::findOrFail($id);

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
        return LeaveRequest::orderBy('id')->get();
    }

    #[Computed]
    public function request()
    {
        if (! $this->requestId) {
            return null;
        }

        return LeaveRequest::find($this->requestId);
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
                    <flux:badge color="blue">TODO: Waiting for PMS</flux:badge>
                </div>
            </div>

            <flux:table class="mt-4">
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
                                @if (! $request->actual_time)
                                    <flux:button
                                        wire:click="recordActualTime({{ $request->id }})"
                                        wire:target="recordActualTime({{ $request->id }})"
                                        >
                                        Record Now
                                    </flux:button> 
                                @else
                                    {{ $request->actual_time }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if (! $request->permitted_end_time)
                                    <flux:text class="text-zinc-400">
                                        Will Not Return
                                    </flux:text>
                                @elseif (! $request->actual_time)
                                    -
                                @elseif ($request->actual_time && $request->actual_return)
                                    {{ $request->actual_return }}
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
                            <flux:table.cell colspan="7" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No visitor record available.
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
                                <flux:heading>Employee ID</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->id }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Employee Name</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->employee_name ?? '-' }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Department</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->department }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Leave Type</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->leave_type }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Start Date</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->date }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Reason</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->purpose }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Submitted At</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->created_at }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Status</flux:heading>
                                <flux:badge color="blue" size="sm">Approved</flux:badge>
                            </div>
                        </div>
                        
                        <flux:separator variant="subtle" />

                        <flux:heading size="lg">Leave Details</flux:heading>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Permitted Time</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->formatted_time }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Will Return</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->request->permitted_end_time ? 'Yes' : 'No' }}</flux:text>
                            </div>
                        </div>

                        <flux:separator variant="subtle" />

                        <flux:heading size="lg">Approval Details</flux:heading>

                        <div class="space-y-2">
                            <div class="flex justify-between items-start bg-gray-400/5 p-4 rounded-xl">
                                <div variant="strong">
                                    <flux:text>Section Chief</flux:text>
                                    <flux:heading class="my-1">Jono</flux:heading>
                                    <flux:text>11/6/2025 11:00</flux:text>
                                </div>
                                <flux:badge color="green" size="sm">Approved</flux:badge>
                            </div>
                            <div class="flex justify-between items-start bg-gray-400/5 p-4 rounded-xl">
                                <div variant="strong">
                                    <flux:text>Department Manager</flux:text>
                                    <flux:heading class="my-1">Saifudin</flux:heading>
                                    <flux:text>11/6/2025 11:00</flux:text>
                                </div>
                                <flux:badge color="green" size="sm">Approved</flux:badge>
                            </div>
                            <div class="flex justify-between items-start bg-gray-400/5 p-4 rounded-xl">
                                <div variant="strong">
                                    <flux:text>HR Manager</flux:text>
                                    <flux:heading class="my-1">Yuga Nugraha</flux:heading>
                                    <flux:text>11/6/2025 11:00</flux:text>
                                </div>
                                <flux:badge color="green" size="sm">Approved</flux:badge>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:modal>
        </div>
    </x-pages::dashboard.layout>
</section>