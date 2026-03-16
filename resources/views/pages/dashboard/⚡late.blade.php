<?php

use Livewire\Component;
use Carbon\Carbon;
use App\Models\Late;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $employee = '';
    public $department = '';

    public function save()
    {
        $validated = $this->validate([
            'employee' => 'required|string|min:3|max:255',
            'department' => 'required|string|min:3'
        ]);

        $arrival = Carbon::now();
        $standard = Carbon::today()->setTime(8,0);

        $minutesLate = 0;

        if ($arrival->greaterThan($standard)) {
            $minutesLate = floor($standard->diffInMinutes($arrival));
        }

        Late::create([
            'name' => $this->employee,
            'department' => $this->department,
            'actual_arrival' => $arrival->format('H:i:s'),
            'minutes_late' => $minutesLate,
            'date' => $arrival->toDateString(),
            'created_by' => Auth::id()
        ]);

        $this->reset(['employee', 'department']);
    }

    #[Computed]
    public function lates()
    {
        return Late::latest()->get();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <flux:heading>Record Late Arrival</flux:heading>
            <flux:text class="mt-2">Record employees who arrive late to the facility.</flux:text>
        
            <div class="flex gap-4 my-6">
                <form wire:submit.prevent="save" class="flex gap-x-4 items-end justify-between w-full">
                    <div class="flex-1">
                        <flux:input wire:model="employee" :label="__('Employee')" type="text" required autofocus />
                    </div>
                    <div class="flex-1">
                        <flux:input wire:model="department" :label="__('Department')" type="text" />
                    </div>
                    <div class="flex-1">
                        <flux:button variant="primary" class="w-full" type="submit">
                            Record Arrival Time
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>

        <div class="border border-accent p-6 rounded-2xl">
            <flux:heading>Late Arrival Records</flux:heading>
            <flux:text class="mt-2">History of late arrivals.</flux:text>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Employee</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    <flux:table.column>Standard Time</flux:table.column>
                    <flux:table.column>Actual Arrival</flux:table.column>
                    <flux:table.column>Minutes Late</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->lates as $late)
                        <flux:table.row>
                            <flux:table.cell>{{ $late->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $late->name }}</flux:table.cell>
                            <flux:table.cell>{{ $late->department }}</flux:table.cell>
                            <flux:table.cell>08.00</flux:table.cell>
                            <flux:table.cell>{{ $late->actual_arrival }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="red" size="sm" inset="top bottom">{{ $late->minutes_late }} minutes</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No late arrival record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::dashboard.layout>
</section>