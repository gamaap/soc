<?php

use App\Models\DraftVehiclePassEntry;
use App\Models\SuperappCarDriverRequest;
use App\Models\VehiclePass;
use Flux\Flux;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;

new class extends Component
{
    public $selectedDate;
    public $selectedStartTime;
    public $selectedEndTime;
    public $draftEntries = [];
    public $completedEntries = [];
    public $filteredEntries = [];
    public $formData = [];
    public $editMode = [];
    public $savedEntries = [];
    public $securityPin = '';
    public $verificationError = '';

    public function mount()
    {
        $this->loadEntries();
    }

    public function loadEntries()
    {
        $this->draftEntries = SuperappCarDriverRequest::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $this->completedEntries = VehiclePass::orderBy('created_at', 'desc')
            ->take(50)
            ->get();
    }

    public function filterEntries()
    {
        $this->validate([
            'selectedDate' => 'required|date',
            'selectedStartTime' => 'required|date_format:H:i',
            'selectedEndTime' => 'required|date_format:H:i',
        ]);

        $startDateTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedStartTime);
        $endDateTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedEndTime);

        $entries = SuperappCarDriverRequest::where('date', $this->selectedDate)
            ->where('departure_time', '>=', $startDateTime->format('H:i'))
            ->where('departure_time', '<=', $endDateTime->format('H:i'))
            ->where('plant_id', 1)
            ->whereNull('starting_km')
            ->whereNull('security_departed_time')
            ->whereNull('ending_km')
            ->whereNull('security_returned_time')
            ->with([
                'drivers.driver',
                'car',
                'purpose',
                'destinations'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

            $this->filteredEntries = $entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'driver_name' => $entry->drivers->map(fn ($driver) => $driver->driver->name ?? '-')->filter()->join(', '),
                    'vehicle_no' => $entry->car->car_vehicle_number ?? '-',
                    'purpose' => $entry->purpose->code ?? $entry->purpose_other ?? '-',
                    'destination' => $entry->destinations->pluck('city')->filter(fn ($city) => $city !== '-')->join(', '),
                    'departure_time' => $entry->departure_time ?? '-',
                    'starting_km' => $entry->starting_km,
                    'security_departed_time' => $entry->security_departed_time,
                    'ending_km' => $entry->ending_km,
                    'security_returned_time' => $entry->security_returned_time,
                ];
            })->toArray();

        $this->initializeFormData();
    }

    private function initializeFormData()
    {
        foreach ($this->filteredEntries as $entry) {
            $this->formData[$entry['id']] = [
                'starting_km' => $entry['starting_km'] ?? '',
                'security_departed_time' => $entry['security_departed_time'] ?? '',
                'ending_km' => $entry['ending_km'] ?? '',
                'security_returned_time' => $entry['security_returned_time'] ?? '',
            ];
            $this->editMode[$entry['id']] = true; // Start in edit mode
        }
    }

    public function saveEntry($entryId)
    {
        $this->validate([
            "formData.{$entryId}.starting_km" => 'required|numeric|min:0',
            "formData.{$entryId}.security_departed_time" => 'required|date_format:H:i',
            "formData.{$entryId}.ending_km" => 'required|numeric|min:0',
            "formData.{$entryId}.security_returned_time" => 'required|date_format:H:i',
        ]);

        $this->savedEntries[$entryId] = true;
        $this->editMode[$entryId] = false; // Switch to display mode

        session()->flash('message', 'Entry staged locally. Submit All to persist.');
    }

    public function toggleEditMode($entryId)
    {
        $this->editMode[$entryId] = !$this->editMode[$entryId];
    }

    private function refreshFilteredEntries()
    {
        if (!$this->selectedDate) {
            return;
        }

        $startDateTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedStartTime);
        $endDateTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedEndTime);

        $entries = SuperappCarDriverRequest::where('date', $this->selectedDate)
            ->where('departure_time', '>=', $startDateTime->format('H:i'))
            ->where('departure_time', '<=', $endDateTime->format('H:i'))
            ->where('plant_id', 1)
            ->whereNull('starting_km')
            ->whereNull('security_departed_time')
            ->whereNull('ending_km')
            ->whereNull('security_returned_time')
            ->whereIn('status', ['Assigned', 'In Transit', 'Completed'])
            ->with([
                'drivers.driver',
                'car',
                'purpose',
                'destinations'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->filteredEntries = $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'driver_name' => $entry->drivers->map(fn ($driver) => $driver->driver->name ?? '-')->filter()->join(', '),
                'vehicle_no' => $entry->car->car_vehicle_number ?? '-',
                'purpose' => $entry->purpose->code ?? $entry->purpose_other ?? '-',
                'destination' => $entry->destinations->pluck('city')->filter(fn ($city) => $city !== '-')->join(', '),
                'departure_time' => $entry->departure_time ?? '-',
                'starting_km' => $entry->starting_km,
                'security_departed_time' => $entry->security_departed_time,
                'ending_km' => $entry->ending_km,
                'security_returned_time' => $entry->security_returned_time,
            ];
        })->toArray();
    }

    public function cancelFilter()
    {
        $this->reset(['selectedDate', 'selectedStartTime', 'selectedEndTime', 'filteredEntries', 'formData', 'editMode', 'savedEntries', 'securityPin', 'verificationError']);
    }

    public function verifyAndSubmit()
    {
        if (count($this->savedEntries) === 0) {
            $this->verificationError = 'No entries have been staged for submission.';
            return;
        }

        if ($this->securityPin !== '112233') {
            $this->verificationError = 'Invalid verification PIN.';
            return;
        }

        $this->verificationError = '';
        $this->submitAll();

        Flux::modal('vehicle-pass-submit-verification')->close();
    }

    public function submitAll()
    {
        foreach (array_keys($this->savedEntries) as $entryId) {
            $data = $this->formData[$entryId] ?? null;

            if (! $data) {
                continue;
            }

            SuperappCarDriverRequest::find($entryId)?->update([
                'starting_km' => $data['starting_km'],
                'security_departed_time' => Carbon::parse($this->selectedDate . ' ' . $data['security_departed_time']),
                'ending_km' => $data['ending_km'] ?: null,
                'security_returned_time' => Carbon::parse($this->selectedDate . ' ' . $data['security_returned_time']),
            ]);
        }

        $this->savedEntries = [];
        $this->editMode = [];
        $this->refreshFilteredEntries();
        $this->loadEntries();
        $this->securityPin = '';

        session()->flash('message', 'All staged entries have been submitted.');
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex justify-between mb-8">
                <div>
                    <flux:heading>Manual Entry - Vehicle Pass</flux:heading>
                    <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.vehicle-pass') }}" wire:current="dashboard.vehicle-pass" wire:navigate>
                        <flux:icon.arrow-left variant="micro" /> Back to Main Menu
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-5 gap-2 items-end">
                <div class="col-span-1">
                    <flux:input wire:model="selectedDate" type="date" label="Select Date" />
                </div>
                <div class="col-span-1">
                    <flux:input wire:model="selectedStartTime" type="time" label="Start Time" />
                </div>
                <div class="col-span-1">
                    <flux:input wire:model="selectedEndTime" type="time" label="End Time" />
                </div>
                <div class="col-span-1">
                    <flux:button wire:click="filterEntries" variant="primary" icon="plus">
                        Add Entry for {{ $selectedDate ? Carbon::parse($selectedDate)->format('d/m/Y') : 'Selected Date' }}
                    </flux:button>
                </div>
            </div>

            <div class="mt-6">
                <flux:heading>Draft Entries</flux:heading>
                @if (count($filteredEntries) > 0)
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Driver Name</flux:table.column>
                            <flux:table.column>Vehicle No.</flux:table.column>
                            <flux:table.column>Purpose</flux:table.column>
                            <flux:table.column>Destination</flux:table.column>
                            <flux:table.column>Departure Time</flux:table.column>
                            <flux:table.column>Starting KM</flux:table.column>
                            <flux:table.column>Leaving Time</flux:table.column>
                            <flux:table.column>Ending KM</flux:table.column>
                            <flux:table.column>Return Time</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($filteredEntries as $entry)
                                <flux:table.row>
                                    <flux:table.cell>{{ $entry['driver_name'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry['vehicle_no'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry['purpose'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry['destination'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry['departure_time'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($editMode[$entry['id']] ?? true)
                                            <flux:input wire:model="formData.{{ $entry['id'] }}.starting_km" type="number" placeholder="KM" />
                                        @else
                                            {{ $formData[$entry['id']]['starting_km'] ?? '-' }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($editMode[$entry['id']] ?? true)
                                            <flux:input wire:model="formData.{{ $entry['id'] }}.security_departed_time" type="time" />
                                        @else
                                            {{ $formData[$entry['id']]['security_departed_time'] ?? '-' }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($editMode[$entry['id']] ?? true)
                                            <flux:input wire:model="formData.{{ $entry['id'] }}.ending_km" type="number" placeholder="KM" />
                                        @else
                                            {{ $formData[$entry['id']]['ending_km'] ?? '-' }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($editMode[$entry['id']] ?? true)
                                            <flux:input wire:model="formData.{{ $entry['id'] }}.security_returned_time" type="time" />
                                        @else
                                            {{ $formData[$entry['id']]['security_returned_time'] ?? '-' }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($editMode[$entry['id']] ?? true)
                                            <flux:button wire:click="saveEntry({{ $entry['id'] }})" variant="ghost" size="sm" icon="save"></flux:button>
                                        @else
                                            <flux:button wire:click="toggleEditMode({{ $entry['id'] }})" variant="ghost" size="sm" icon="pencil">
                                                Edit
                                            </flux:button>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    {{-- <div class="mt-4 flex gap-2">
                        <flux:button wire:click="cancelFilter" variant="ghost">Cancel Filter</flux:button>
                    </div> --}}
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Driver Name</flux:table.column>
                            <flux:table.column>Vehicle No.</flux:table.column>
                            <flux:table.column>Purpose</flux:table.column>
                            <flux:table.column>Destination</flux:table.column>
                            <flux:table.column>Departure Time</flux:table.column>
                            <flux:table.column>Starting KM</flux:table.column>
                            <flux:table.column>Leaving Time</flux:table.column>
                            <flux:table.column>Ending KM</flux:table.column>
                            <flux:table.column>Return Time</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell colspan="10" class="text-center text-gray-500 italic">
                                    No draft entries found.
                                </flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>

            <div class="mt-4">
                @if(count($savedEntries) > 0)
                    <flux:modal.trigger name="vehicle-pass-submit-verification">
                        <flux:button variant="primary" icon="check">
                            Submit All ({{ count($savedEntries) }})
                        </flux:button>
                    </flux:modal.trigger>
                @else
                    <flux:button variant="primary" disabled>
                        Submit All (0)
                    </flux:button>
                @endif
            </div>
        </div>

        <flux:modal name="vehicle-pass-submit-verification" class="w-[1000px]!">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Verify and Submit All Drafts</flux:heading>
                    <flux:text class="mt-2">Review all draft entries and authorize with head of security PIN.</flux:text>
                </div>

                <div>
                    <flux:input wire:model="securityPin" label="Head of Security PIN" type="password" placeholder="Enter 6-digit PIN" />
                    @if($verificationError)
                        <p class="text-sm text-red-600 mt-2">{{ $verificationError }}</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" x-on:click="$flux.modal('visitor-submit-verification').close()">Cancel</flux:button>
                    <flux:button type="button" variant="primary" wire:click="verifyAndSubmit">Verify & Submit</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-pages::dashboard.layout>
</section>