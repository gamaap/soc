<?php

use Livewire\Component;
use Carbon\Carbon;
use App\Models\Breaks;
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

        $return = Carbon::now();
        $standard = Carbon::today()->setTime(13,0);

        $minutesLate = 0;

        if ($return->greaterThan($standard)) {
            $minutesLate = floor($standard->diffInMinutes($return));
        }

        Breaks::create([
            'name' => $this->employee,
            'department' => $this->department,
            'actual_return' => $return->format('H:i:s'),
            'minutes_late' => $minutesLate,
            'date' => $return->toDateString(),
            'created_by' => Auth::id()
        ]);

        $this->reset(['employee', 'department']);
    }

    #[Computed]
    public function breaks()
    {
        return Breaks::latest()->get();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <flux:heading>Record Employee Return Time</flux:heading>
            <flux:text class="mt-2">Record when employee return from break.</flux:text>
        
            <div class="flex gap-4 my-6">
                <form wire:submit.prevent="save" action="" class="flex gap-x-4 items-end justify-between w-full">
                    <div class="flex-1">
                        <flux:input wire:model="employee" :label="__('Employee')" type="text" required autofocus />
                    </div>
                    <div class="flex-1">
                        <flux:input wire:model="department" :label="__('Department')" type="text" />
                    </div>
                    <div class="flex-1">
                        <flux:button variant="primary" class="w-full" type="submit">
                            Record Return Time Now
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>

        <div class="border border-accent p-6 rounded-2xl">
            <flux:heading>Break Time History</flux:heading>
            <flux:text class="mt-2">View all break time records.</flux:text>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Employee</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    <flux:table.column>Standard Time</flux:table.column>
                    <flux:table.column>Actual Return</flux:table.column>
                    <flux:table.column>Minutes Late</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->breaks as $break)
                        <flux:table.row>
                            <flux:table.cell>{{ $break->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $break->name }}</flux:table.cell>
                            <flux:table.cell>{{ $break->department }}</flux:table.cell>
                            <flux:table.cell>13.00</flux:table.cell>
                            <flux:table.cell>{{ $break->actual_return }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="red" size="sm" inset="top bottom">{{ $break->minutes_late }} minutes</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No break time record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::dashboard.layout>
</section>