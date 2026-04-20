<?php

use Livewire\WithPagination;
use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\VehiclePass;
use App\Models\EmployeePass;
use App\Models\EmployeeMasterPass;
use App\Models\SuperappCarDriverRequest;
use App\Models\SuperappCarDriverRequestHistory;
use Flux\Flux;
use Carbon\Carbon;

new class extends Component
{
    use WithPagination;

    public $vehiclePassId;
    public $starting_km;
    public $ending_km;
    public $name;
    public $department;
    public $license_plate;
    public $vehicle_type;
    public $employeeMaster;
    public $departmentMaster;
    public $licensePlateMaster = '';
    public $licensePlates = [];
    public $plateOptions = [];

    #[Computed]
    public function vehiclePasses()
    {
        return SuperappCarDriverRequest::select('id', 'code', 'security_departed_time', 'security_returned_time', 'starting_km', 'ending_km', 'status', 'purpose_code', 'purpose_other')
            ->with(['drivers' => function($query) {
                $query->select('id', 'reff_code', 'driver_code');
            }, 'drivers.driver' => function($query) {
                $query->select('id', 'code', 'name');
            }, 'car' => function($query) {
                $query->select('id', 'reff_code', 'car_vehicle_number');
            }, 'purpose' => function($query) {
                $query->select('id', 'code', 'name');
            }, 'destinations' => function($query) {
                $query->select('id', 'reff_code', 'destination', 'city');
            }])
            ->whereIn('status', ['Assigned', 'In Transit', 'Completed'])
            ->where('plant_id', 1)
            ->where('date', Carbon::today()->toDateString())
            ->orderByRaw('security_departed_time IS NULL DESC, security_departed_time ASC')
            ->paginate(10);
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|min:3',
            'department' => 'required|string|min:3',
            'license_plate' => 'required|min:3',
        ]);

        EmployeePass::create([
            'date' => now()->toDateString(),
            'name' => $this->name,
            'department' => $this->department,
            'license_plate' => $this->license_plate,
            'entry_time' => now()->format('H:i:s'),
            'created_by' => auth()->id(),
        ]);

        $this->reset(['name', 'department', 'license_plate', 'plateOptions']);

        return redirect(request()->header('Referer'));
    }

    public function addLicensePlate()
    {
        $this->validate([
            'licensePlateMaster' => 'required|string|min:3',
        ]);

        if (! in_array($this->licensePlateMaster, $this->licensePlates)) {
            $this->licensePlates[] = $this->licensePlateMaster;
        }

        $this->licensePlateMaster = '';
    }

    public function removeLicensePlate($index)
    {
        if (isset($this->licensePlates[$index])) {
            array_splice($this->licensePlates, $index, 1);
        }
    }

    public function saveMasterData()
    {
        $this->validate([
            'employeeMaster' => 'required|string|min:3',
            'departmentMaster' => 'required|string|min:3',
            'licensePlates' => 'required|array|min:1',
        ]);

        foreach ($this->licensePlates as $plate) {
            EmployeeMasterPass::create([
                'employee_name' => $this->employeeMaster,
                'department' => $this->departmentMaster,
                'license_plate' => $plate,
                'created_by' => auth()->id()
            ]);
        }

        $this->reset(['employeeMaster', 'departmentMaster', 'licensePlateMaster', 'licensePlates', 'plateOptions']);
    }

    public function loadEmployeeMaster(string $employeeName)
    {
        $matches = EmployeeMasterPass::where('employee_name', $employeeName)
            ->get();

        if ($matches->isEmpty()) {
            $this->departmentMaster = '';
            $this->plateOptions = [];
            return;
        }

        $this->departmentMaster = $matches->first()->department;
        $this->plateOptions = $matches->pluck('license_plate')->unique()->values()->toArray();

        if (count($this->plateOptions) === 1) {
            $this->licensePlateMaster = $this->plateOptions[0];
        }
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

        $pass = SuperappCarDriverRequest::find($this->vehiclePassId);

        $pass->update([
            'starting_km' => $this->starting_km,
            'security_departed_time' => Carbon::now(),
        ]);

        SuperappCarDriverRequestHistory::create([
            'reff_code' => $pass->code,
            'status' => 'Security In Transit',
            'from' => 'Admin',
            'color' => '#0D99FF'
        ]);

        $this->reset('starting_km', 'vehiclePassId');

        Flux::modal('record-leaving')->close();
    }

    public function recordReturn()
    {
        $pass = SuperappCarDriverRequest::find($this->vehiclePassId);

        $this->validate([
            'ending_km' => 'required|integer|gte:' . $pass->starting_km
        ]);

        $pass->update([
            'ending_km' => $this->ending_km,
            'security_returned_time' => Carbon::now(),
        ]);

        SuperappCarDriverRequestHistory::create([
            'reff_code' => $pass->code,
            'status' => 'Security Returned Time',
            'from' => 'Admin',
            'color' => '#33FF00'
        ]);

        $this->reset('ending_km', 'vehiclePassId');

        Flux::modal('record-return')->close();
    }

    #[Computed]
    public function employeePasses()
    {
        return EmployeePass::where(function ($query) {
            $query->whereDate('date', today())
                  ->orWhere(function ($q) {
                      $q->whereNull('leaving_time')
                        ->whereDate('date', '<', today());
                  });
        })
        ->orderByRaw('leaving_time IS NULL DESC')
        ->orderBy('date', 'asc')
        ->orderBy('entry_time', 'asc')
        ->paginate(10);
    }

    #[Computed]
    public function employeeMasterPasses()
    {
        return EmployeeMasterPass::latest()->paginate(10);
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex items-center justify-between">
                <div>
                    <div>
                        <flux:heading>Company Vehicle Pass</flux:heading>
                        <flux:text class="mt-2">Track company vehicle movements with milleage records.</flux:text>
                    </div>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.vehicle-pass-manual-entry') }}"
                        wire:current="dashboard.vehicle-pass-manual-entry" wire:navigate>
                        <flux:icon.plus variant="micro" /> Manual Entry
                    </flux:button>
                </div>
            </div>

            <flux:table class="mt-4" :paginate="$this->vehiclePasses">
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
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->vehiclePasses as $pass)
                    <flux:table.row wire:key="vehicle-pass-{{ $pass->id }}">
                        <flux:table.cell>
                            {!! $pass->drivers->map(fn($driver) => $driver->driver->name)->join('<br>') !!}
                        </flux:table.cell>
                        <flux:table.cell>{{ $pass->car->car_vehicle_number ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $pass->purpose->code }}</flux:table.cell>
                        <flux:table.cell>
                            {!! $pass->destinations->pluck('city')->filter(fn($city) => $city !== '-')->join('<br>') !!}
                        </flux:table.cell>
                        <flux:table.cell>{{ $pass->starting_km ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($pass->security_departed_time)
                            {{ Carbon::parse($pass->security_departed_time)->format('d/m/Y') }}<br>
                            {{ Carbon::parse($pass->security_departed_time)->format('H:i') }}
                            @else
                            -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $pass->ending_km ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($pass->security_returned_time)
                            {{ Carbon::parse($pass->security_returned_time)->format('d/m/Y') }}<br>
                            {{ Carbon::parse($pass->security_returned_time)->format('H:i') }}
                            @else
                            -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if (! $pass->starting_km)
                            <flux:button icon="log-in" variant="primary" size="sm"
                                wire:click="openLeavingModal({{ $pass->id }})"
                                wire:target="openLeavingModal({{ $pass->id }})">
                                Record Leaving
                            </flux:button>
                            @elseif ($pass->starting_km && ! $pass->ending_km)
                            <flux:button icon="log-out" size="sm" wire:click="openReturnModal({{ $pass->id }})"
                                wire:target="openReturnModal({{ $pass->id }})">
                                Record Return
                            </flux:button>
                            @else
                            <flux:badge size="sm" class="w-full items-center justify-center" color="green">Completed
                            </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <a href="https://superapp.ewindo.co.id/api/v1/rrs/r-car-driver-requests/driver-assignment-letter/{{ $pass->code }}"
                                target="_blank">
                                <x-icon.eye variant="mini" />
                            </a>
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
                            <flux:text class="mt-8">Enter Starting KM for this vehicle and automatically record leaving
                                time.</flux:text>
                        </div>
                        <flux:input wire:model="starting_km" type="number" label="Starting KM"
                            placeholder="Enter Starting KM" required />
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
                            <flux:text class="mt-8">Enter Ending KM for this vehicle and automatically record return
                                time.</flux:text>
                        </div>
                        <flux:input wire:model="ending_km" type="number" label="Ending KM" placeholder="Enter Ending KM"
                            required />
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
                <div class="flex items-center justify-end gap-2">
                    <flux:modal.trigger name="master-data-vehicle">
                        <flux:button variant="primary">Master Data</flux:button>
                    </flux:modal.trigger>
                    <flux:button variant="outline" href="{{ route('dashboard.employee-pass-manual-entry') }}"
                        wire:current="dashboard.employee-pass-manual-entry" wire:navigate>
                        <flux:icon.plus variant="micro" /> Manual Entry
                    </flux:button>
                </div>
            </div>
            <div class="my-6">
                <form wire:submit.prevent="save" action="">
                    <div class="grid grid-cols-12 gap-4 items-end">
                        <div class="autoComplete_wrapper col-span-3" wire:ignore>
                            <flux:input wire:model="name" id="employee-vehicle-pass" :label="__('Employee Name')"
                                type="text" autocomplete="off" required autofocus />
                        </div>
                        <div class="col-span-3">
                            <flux:input wire:model="department" :label="__('Department')" type="text"
                                autocomplete="off" />
                        </div>
                        <div class="col-span-3">
                            @if (count($plateOptions) >= 1)
                            <flux:label>Vehicle Number</flux:label>
                            <flux:select wire:model="license_plate" class="mt-2">
                                <flux:select.option value="">Select a vehicle</flux:select.option>
                                @foreach ($plateOptions as $plate)
                                <flux:select.option value="{{ $plate }}">{{ $plate }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @else
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input id="license-vehicle-pass" wire:model="license_plate"
                                    :label="__('Vehicle Number')" type="text" autocomplete="off" />
                            </div>
                            @endif
                        </div>
                        <div class="col-span-3">
                            <flux:button variant="primary" class="w-full items-center justify-center" type="submit">
                                Record Entry
                            </flux:button>
                        </div>
                    </div>
                </form>
            </div>
            <flux:separator variant="subtle" />
            <flux:table class="mt-4" :paginate="$this->employeePasses">
                <flux:table.columns>
                    <flux:table.column>Employee Name</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    <flux:table.column>Vehicle Number</flux:table.column>
                    <flux:table.column>Entry Time</flux:table.column>
                    <flux:table.column>Leaving Time</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->employeePasses as $pass)
                    <flux:table.row>
                        <flux:table.cell>{{ $pass->name }}</flux:table.cell>
                        <flux:table.cell>{{ $pass->department }}</flux:table.cell>
                        <flux:table.cell>{{ $pass->license_plate }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col text-center">
                                <span>
                                    {{ $pass->entry_time }}
                                </span>
                                @if (! Carbon::parse($pass->date)->isToday())
                                    <flux:badge color="red" class="mt-1">
                                        {{ Carbon::parse($pass->date)->format('d/m/Y') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
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
                                No employee vehicle pass record available.
                            </flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:modal name="master-data-vehicle" class="max-w-7xl">
            <div class="space-y-6">
                <div>
                    <flux:heading>Master Data - Vehicle</flux:heading>
                    <flux:text class="mt-2">Add employee vehicle data used in company.</flux:text>
                </div>
                <div>
                    <form wire:submit.prevent="saveMasterData" class="grid grid-cols-13 gap-4 items-end">
                        <div class="col-span-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input id="employee-vehicle-master" wire:model="employeeMaster"
                                    :label="__('Employee Name')" type="text" autocomplete="off" required autofocus />
                            </div>
                        </div>
                        <div class="col-span-4">
                            <flux:input wire:model="departmentMaster" :label="__('Department')" type="text" />
                        </div>
                        <div class="col-span-4">
                            <flux:input wire:model="licensePlateMaster" :label="__('Vehicle Number')" type="text" />
                        </div>
                        <div class="col-span-1">
                            <flux:button variant="ghost" class="w-full" type="button"
                                wire:click.prevent="addLicensePlate">
                                <flux:icon.plus variant="mini" />
                            </flux:button>
                        </div>
                        <div class="col-span-13">
                            <flux:button variant="primary" class="w-full" type="submit">
                                Submit
                            </flux:button>
                        </div>
                    </form>

                    @if(count($licensePlates))
                    <div class="mt-4 grid grid-cols-12 gap-2">
                        @foreach($licensePlates as $idx => $plate)
                        <div class="col-span-3 flex items-center gap-2">
                            <flux:badge>{{ $plate }}</flux:badge>
                            <flux:button size="sm" variant="danger" type="button"
                                wire:click.prevent="removeLicensePlate({{ $idx }})">
                                Remove
                            </flux:button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            <div class="mt-8">
                <flux:table :paginate="$this->employeeMasterPasses">
                    <flux:table.columns>
                        <flux:table.column>Employee Name</flux:table.column>
                        <flux:table.column>Department</flux:table.column>
                        <flux:table.column>Vehicle Number</flux:table.column>
                        <flux:table.column>Action</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->employeeMasterPasses as $pass)
                        <flux:table.row>
                            <flux:table.cell>{{ $pass->employee_name }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->department }}</flux:table.cell>
                            <flux:table.cell>{{ $pass->license_plate }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button variant="ghost">
                                    <flux:icon.pencil-square variant="micro" />
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                        @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No employee vehicle data available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:modal>
    </x-pages::dashboard.layout>

</section>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    if (window.autoCompleteJS) {
        window.autoCompleteJS.destroy();
    }
    window.autoCompleteJS = new autoComplete({
        selector: "#employee-vehicle-master",
        data: {
            src: async (query) => {
                try {
                    const source = await fetch(`/employees/api?search=${encodeURIComponent(query)}`);
                    const data = await source.json();

                    return data;
                } catch (error) {
                    return error;
                }
            },
            keys: ["fullname"],
        },
        resultsList: {
            maxResults: 50
        },
        resultItem: {
            highlight: true
        },
        events: {
            input: {
                selection: (event) => {
                    const selection = event.detail.selection.value;

                    window.autoCompleteJS.input.value = selection.fullname;
                    $wire.set('employeeMaster', selection.fullname);
                    $wire.set('departmentMaster', selection.department?.name ?? '');
                }
            }
        }
    });

    if (window.autoCompleteJS2) {
        window.autoCompleteJS2.destroy();
    }
    window.autoCompleteJS2 = new autoComplete({
        selector: "#employee-vehicle-pass",
        data: {
            src: async (query) => {
                try {
                    const source = await fetch(`/employee-master/api?search=${encodeURIComponent(query)}`);
                    const data = await source.json();

                    return data;
                } catch (error) {
                    return error;
                }
            },
            keys: ["employee_name"],
        },
        resultsList: {
            maxResults: 50
        },
        resultItem: {
            highlight: true
        },
        events: {
            input: {
                selection: (event) => {
                    const selection = event.detail.selection.value;

                    window.autoCompleteJS2.input.value = selection.employee_name;
                    $wire.set('name', selection.employee_name);
                    $wire.set('department', selection.department);

                    const plates = selection.license_plates ?? [];
                    $wire.set('plateOptions', plates);

                    if (plates.length === 1) {
                        $wire.set('licensePlateMaster', plates[0]);
                    } else {
                        $wire.set('licensePlateMaster', '');
                    }

                    $wire.call('loadEmployeeMaster', selection.employee_name);

                }
            }
        }
    });

    if (window.autoCompleteJS3) {
        window.autoCompleteJS3.destroy();
    }
    window.autoCompleteJS3 = new autoComplete({
        selector: "#license-vehicle-pass",
        threshold: 1,
        data: {
            src: async (query) => {
                try {
                    const source = await fetch(`/employee-master/api?search=${encodeURIComponent(query)}`);
                    const data = await source.json();

                    return data.flatMap((item) => {
                        return (item.license_plates || []).map((plate) => ({
                            employee_name: item.employee_name,
                            department: item.department,
                            license_plate: plate,
                            license_plates: item.license_plates || []
                        }));
                    });
                } catch (error) {
                    return error;
                }
            },
            keys: ["license_plate"],
        },
        resultsList: {
            maxResults: 50
        },
        resultItem: {
            highlight: true
        },
        events: {
            input: {
                selection: (event) => {
                    const selection = event.detail.selection.value;

                    window.autoCompleteJS3.input.value = selection.license_plate;
                    $wire.set('license_plate', selection.license_plate);
                    $wire.set('name', selection.employee_name);
                    $wire.set('department', selection.department);
                    $wire.set('plateOptions', selection.license_plates || []);

                    if (selection.license_plates && selection.license_plates.length === 1) {
                        $wire.set('license_plate', selection.license_plates[0]);
                    }
                }
            }
        }
    });
</script>