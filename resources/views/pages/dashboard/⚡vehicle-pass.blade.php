<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\VehiclePass;
use App\Models\EmployeePass;
use Flux\Flux;

new class extends Component
{
    public $vehiclePassId;
    public $starting_km;
    public $ending_km;
    public $name;
    public $department;
    public $license_plate;
    public $vehicle_type;

    #[Computed]
    public function vehiclePasses()
    {
        return VehiclePass::orderBy('id')->get();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|min:3',
            'department' => 'required|string|min:3',
            'license_plate' => 'required|min:3',
            'vehicle_type' => 'required'
        ]);

        EmployeePass::create([
            'date' => now()->toDateString(),
            'name' => $this->name,
            'department' => $this->department,
            'license_plate' => $this->license_plate,
            'vehicle_type' => $this->vehicle_type,
            'entry_time' => now()->format('H:i:s'),
            'created_by' => auth()->id(),
        ]);

        $this->reset();
    }

    public function checkOut($id)
    {
        $employee = EmployeePass::findOrFail($id);

        $employee->update([
            'leaving_time' => now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);
    }

    public function openLeavingModal($id)
    {
        $this->vehiclePassId = $id;

        Flux::modal('record-leaving')->show();
    }

    public function openReturnModal($id)
    {
        $this->vehiclePassId = $id;

        Flux::modal('record-return')->show();
    }

    public function recordLeaving()
    {
        $this->validate([
            'starting_km' => 'required|integer|min:0'
        ]);

        $pass = VehiclePass::find($this->vehiclePassId);

        $pass->update([
            'starting_km' => $this->starting_km,
            'leaving_time' => now()->format('H:i:s')
        ]);

        $this->reset('starting_km', 'vehiclePassId');

        Flux::modal('record-leaving')->close();
    }

    public function recordReturn()
    {
        $pass = VehiclePass::find($this->vehiclePassId);

        $this->validate([
            'ending_km' => 'required|integer|gte:' . $pass->starting_km
        ]);

        $pass->update([
            'ending_km' => $this->ending_km,
            'return_time' => now()->format('H:i:s')
        ]);

        $this->reset('ending_km', 'vehiclePassId');

        Flux::modal('record-return')->close();
    }

    #[Computed]
    public function employeePasses()
    {
        return EmployeePass::latest()->get();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex items-center justify-between">
                 <div>
                    <flux:heading>Company Vehicle Pass</flux:heading>
                    <flux:text class="mt-2">Track company vehicle movements with milleage records.</flux:text>
                </div>
                <div>
                    <flux:badge color="blue">TODO: Connect with Driver App (SuperApp)</flux:badge>
                </div>
            </div>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Driver Name</flux:table.column>
                    <flux:table.column>Vehicle No.</flux:table.column>
                    <flux:table.column>Purpose</flux:table.column>
                    <flux:table.column>Destination</flux:table.column>
                    <flux:table.column>Starting KM</flux:table.column>
                    <flux:table.column>Leaving Time</flux:table.column>
                    <flux:table.column>Ending KM</flux:table.column>
                    <flux:table.column>Return Time</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->vehiclePasses as $pass)
                        <flux:table.row wire:key="vehicle-pass-{{ $pass->id }}">
                            <flux:table.cell>{{ $pass->name }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->purpose }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->destination }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->starting_km ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->leaving_time ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->ending_km ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->return_time ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @if (! $pass->starting_km)
                                    <flux:button 
                                        icon="log-in" 
                                        variant="primary" 
                                        size="sm"
                                        wire:click="openLeavingModal({{ $pass->id }})"
                                        wire:target="openLeavingModal({{ $pass->id }})"
                                        >
                                        Record Leaving
                                    </flux:button>
                                @elseif ($pass->starting_km && ! $pass->ending_km)
                                    <flux:button 
                                        icon="log-out" 
                                        size="sm"
                                        wire:click="openReturnModal({{ $pass->id }})"
                                        wire:target="openReturnModal({{ $pass->id }})"
                                        >
                                        Record Return
                                    </flux:button>
                                @else
                                    <flux:badge size="sm" color="green">Completed</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No company vehicle pass record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <flux:modal name="record-leaving" class="md:w-96" :dismissible="false">
                <form wire:submit.prevent="recordLeaving">
                    <div class="space-y-6">
                        <div>
                            <flux:text class="mt-8">Enter Starting KM for this vehicle and automatically record leaving time.</flux:text>
                        </div>
                        <flux:input wire:model="starting_km" type="number" label="Starting KM" placeholder="Enter Starting KM" required />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary" class="w-full">Record Leaving</flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="record-return" class="md:w-96" :dismissible="false">
                <form wire:submit.prevent="recordReturn">
                    <div class="space-y-6">
                        <div>
                            <flux:text class="mt-8">Enter Ending KM for this vehicle and automatically record return time.</flux:text>
                        </div>
                        <flux:input wire:model="ending_km" type="number" label="Ending KM" placeholder="Enter Ending KM" required />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary" class="w-full">Record Return</flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>
        </div>

        <div class="border border-accent p-6 rounded-2xl">
            <div class="flex items-center justify-between">
                 <div>
                    <flux:heading>Employee Pass</flux:heading>
                    <flux:text class="mt-2">Track employee personal vehicle entries.</flux:text>
                </div>
            </div>
            <div class="my-6">
                <form wire:submit.prevent="save" action="">
                    <div class="grid grid-cols-4 gap-4">
                        <flux:input wire:model="name" :label="__('Employee Name')" type="text" required autofocus />
                        <flux:input wire:model="department" :label="__('Department')" type="text" />
                        <flux:input wire:model="license_plate" :label="__('Vehicle Number')" type="text" />
                        <flux:input wire:model="vehicle_type" :label="__('Vehicle Type')" type="text" />
                    </div>
                    <div class="flex items-end justify-end mt-4">
                        <flux:button variant="primary" class="w-sm" type="submit">
                            Record Entry
                        </flux:button>
                    </div>
                </form>
            </div>
            <flux:separator variant="subtle" />
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Employee Name</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    <flux:table.column>Vehicle Number</flux:table.column>
                    <flux:table.column>Vehicle Type</flux:table.column>
                    <flux:table.column>Entry Time</flux:table.column>
                    <flux:table.column>Leaving Time</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->employeePasses as $pass)
                        <flux:table.row>
                            <flux:table.cell>{{ $pass->name }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->department }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->vehicle_type }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->entry_time }}</flux:table.cell>
                            <flux:table.cell>
                                @if (! $pass->leaving_time)
                                    <flux:button wire:click="checkOut({{ $pass->id }})">Record Leaving</flux:button>
                                @else
                                    {{ $pass->leaving_time }}
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No employe vehicle pass record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::dashboard.layout>

</section>