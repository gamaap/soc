<?php

use Livewire\Component;
use Carbon\Carbon;
use App\Models\NightShift;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $employee = '';
    public $department = '';

    public function checkIn()
    {
        $this->validate([
            'employee' => 'required|string|min:3|max:255',
            'department' => 'required|string|min:3'
        ]);

        NightShift::where('name', $this->employee)
            ->whereDate('date', today())
            ->whereNull('check_out_time')
            ->exists();

        $now = Carbon::now();

        NightShift::create([
            'name' => $this->employee,
            'department' => $this->department,
            'date' => $now->toDateString(),
            'check_in_time' => $now->format('H:i:s'),
            'created_by' => Auth::id()
        ]);

        $this->reset(['employee', 'department']);
    }

    public function checkOut($id)
    {
        $shift = NightShift::findOrFail($id);

        $shift->update([
            'check_out_time' => Carbon::now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);
    }

    #[Computed]
    public function shifts()
    {
        return NightShift::latest()->get();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <flux:heading>Record Night Shift Attendance</flux:heading>
            <flux:text class="mt-2">Record employee check-in and check-out times for night shift.</flux:text>
        
            <div class="flex gap-4 my-6">
                <form wire:submit.prevent="checkIn" action="" class="flex gap-x-4 items-end justify-between w-full">
                    <div class="flex-1">
                        <flux:input wire:model="employee" :label="__('Employee')" type="text" required autofocus />
                    </div>
                    <div class="flex-1">
                        <flux:input wire:model="department" :label="__('Department')" type="text" />
                    </div>
                    <div class="flex-1">
                        <flux:button variant="primary" class="w-full" type="submit">
                            Record Check In Time
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>

        <div class="border border-accent p-6 rounded-2xl">
            <flux:heading>Night Shift Records</flux:heading>
            <flux:text class="mt-2">View all night shift attendance records.</flux:text>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Employee</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    <flux:table.column>Check-In Time</flux:table.column>
                    <flux:table.column>Check-Out Time</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->shifts as $shift)
                        <flux:table.row>
                            <flux:table.cell>{{ $shift->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $shift->name }}</flux:table.cell>
                            <flux:table.cell>{{ $shift->department }}</flux:table.cell>
                            <flux:table.cell>{{ $shift->check_in_time }}</flux:table.cell>
                            <flux:table.cell>
                                    @if (! $shift->check_out_time)
                                        <flux:button wire:click="checkOut({{ $shift->id }})" wire:target="checkOut({{ $shift->id }})">Record Check-Out</flux:button>
                                    @else
                                        {{ $shift->check_out_time }}
                                    @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No night shift record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::dashboard.layout>
</section>