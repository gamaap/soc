<?php

use App\Models\DraftEmployeePassEntry;
use App\Models\EmployeePass;
use App\Models\EmployeeMasterPass;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
	use WithPagination;
	
    public $selectedDate;
    public $draftEntries = [];
    public $completedEntries = [];
    public $name;
    public $department;
    public $license_plate;
    public $entry_time;
    public $leaving_time;
    public $securityPin;
    public $verificationError;
    public $plateOptions = [];
    public $licensePlateMaster = '';

    public function mount()
    {
        $this->loadEntries();
    }

    public function loadEntries()
    {
        $query = DraftEmployeePassEntry::where('user_id', auth()->id());

        if ($this->selectedDate) {
            $query->whereDate('date', $this->selectedDate);
        }

        $this->draftEntries = $query->orderBy('created_at', 'desc')
            ->get();

        $completedQuery = EmployeePass::orderBy('created_at', 'desc');

        if ($this->selectedDate) {
            $completedQuery->whereDate('date', $this->selectedDate);
        }

        $this->completedEntries = $completedQuery->take(50)
            ->get();
    }

    public function addEntry()
    {
        $this->validate([
            'name' => 'required|string|min:3',
            'department' => 'required|string|min:3',
            'license_plate' => 'required|min:3',
        ]);

        DraftEmployeePassEntry::create([
            'date' => $this->selectedDate,
            'name' => $this->name,
            'department' => $this->department,
            'license_plate' => $this->license_plate,
            'entry_time' => $this->entry_time,
            'leaving_time' => $this->leaving_time,
            'created_by' => auth()->id(),
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name', 'department', 'license_plate']);
        $this->loadEntries();

        session()->flash('message', 'Entry added to draft successfully.');

        Flux::modal('employee-pass-manual-entry')->close();
    }

    public function submitAll()
    {
        $drafts = DraftEmployeePassEntry::where('user_id', auth()->id())
            ->whereDate('date', $this->selectedDate)
            ->get();

        foreach ($drafts as $draft) {
            EmployeePass::create([
                'date' => $draft->date,
                'name' => $draft->name,
                'department' => $draft->department,
                'license_plate' => $draft->license_plate,
                'entry_time' => $draft->entry_time,
                'leaving_time' => $draft->leaving_time,
                'created_by' => auth()->id(),
            ]);

            $draft->delete();
        }

        $this->loadEntries();

        session()->flash('message', 'All draft entries submitted successfully.');
    }

    public function verifyAndSubmit()
    {
        if ($this->securityPin !== '112233') {
            $this->verificationError = 'Invalid head of security PIN.';
            return;
        }

        $this->verificationError = '';

        $this->submitAll();

        $this->securityPin = '';

        Flux::modal('employee-pass-submit-verification')->close();
    }

    public function deleteDraft($id)
    {
        DraftEmployeePassEntry::find($id)?->delete();
        $this->loadEntries();
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

};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex justify-between mb-8">
                <div>
                    <flux:heading>Manual Entry - Employee Pass</flux:heading>
                    <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.vehicle-pass') }}" wire:current="dashboard.vehicle-pass" wire:navigate>
                        <flux:icon.arrow-left variant="micro" /> Back to Main Menu
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2 items-end">
                <div class="col-span-3">
                    <flux:input wire:model="selectedDate" type="date" label="Select Date" wire:change="loadEntries" class="w-full" />
                </div>
                <div class="col-span-1">
                    <flux:modal.trigger name="employee-pass-manual-entry">
                        <flux:button variant="primary" class="place-items-end">
                            <flux:icon.plus variant="micro" /> Add Entry for {{ $selectedDate ? Carbon::parse($selectedDate)->format('d/m/Y') : 'Selected Date' }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <div class="mt-6">
                <flux:heading>Draft Entries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Employees</flux:table.column>
                        <flux:table.column>Department</flux:table.column>
                        <flux:table.column>License Plate</flux:table.column>
                        <flux:table.column>Entry Time</flux:table.column>
                        <flux:table.column>Leaving Time</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($draftEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->license_plate }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->entry_time }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->leaving_time }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button wire:click="deleteDraft({{ $entry->id }})" variant="ghost" size="sm">
                                        <flux:icon.trash variant="mini" class="text-red-500" />
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center text-gray-500 italic">
                                    No draft entries found.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="mt-4">
                <flux:modal.trigger name="employee-pass-submit-verification">
                    <flux:button variant="primary" :disabled="$draftEntries->count() === 0">Submit All ({{ $draftEntries->count() }})</flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mt-6">
                <flux:heading>Completed Entries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Employees</flux:table.column>
                        <flux:table.column>Department</flux:table.column>
                        <flux:table.column>License Plate</flux:table.column>
                        <flux:table.column>Entry Time</flux:table.column>
                        <flux:table.column>Leaving Time</flux:table.column>
                        <flux:table.column>Confirmed By</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($completedEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->license_plate }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->entry_time }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->leaving_time }}</flux:table.cell>
                                <flux:table.cell>{{ Auth::user()->name }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center text-gray-500 italic">
                                    No completed entries found.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>

        <flux:modal name="employee-pass-manual-entry" class="w-100!">
            <form wire:submit="addEntry">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Add Employee Pass Arrival Records</flux:heading>
                        <flux:text class="mt-2">Manually enter employee pass arrival information for employees.</flux:text>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <flux:input wire:model="selectedDate" class="col-span-1" label="Date" type="date" />
                        </div>
                        <div class="col-span-2">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input id="employee-vehicle-pass" wire:model="name" label="Employee Name" placeholder="Select Employee" autocomplete="off" />
                                @error('employee') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-span-2">
                            <flux:input wire:model="department" label="Department" autocomplete="off" />
                        </div>
                        <div class="col-span-2">
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
                                    <flux:input id="license-vehicle-pass" wire:model="license_plate" :label="__('Vehicle Number')" type="text" autocomplete="off" />
                                </div>
                            @endif
                        </div>
                        <flux:input wire:model="entry_time" class="col-span-1" label="Entry Time" type="time" />
                        <flux:input wire:model="leaving_time" class="col-span-1" label="Leaving Time" type="time" />
                    </div>
                    <div class="flex">
                        <flux:spacer />
                        <div class="flex gap-2 items-end justify-end">
                            <flux:button type="button" x-on:click="$flux.modal('late-manual-entry').close()">Cancel</flux:button>
                            <flux:button type="submit" variant="primary" icon="save">
                                Save as Draft
                            </flux:button>
                        </div>
                    </div>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="employee-pass-submit-verification" class="w-[1000px]!">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Verify and Submit All Drafts</flux:heading>
                    <flux:text class="mt-2">Review all draft entries and authorize with head of security PIN.</flux:text>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Employees</flux:table.column>
                        <flux:table.column>Department</flux:table.column>
                        <flux:table.column>License Plate</flux:table.column>
                        <flux:table.column>Entry Time</flux:table.column>
                        <flux:table.column>Leaving Time</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($draftEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->license_plate }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->entry_time }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->leaving_time }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-gray-500">No draft entries available.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

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

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    const autoCompleteJS2 = new autoComplete({
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

                    autoCompleteJS2.input.value = selection.employee_name;
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

    const autoCompleteJS3 = new autoComplete({
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

                    autoCompleteJS3.input.value = selection.license_plate;
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