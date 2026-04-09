<?php

use App\Models\DraftNightShiftEntry;
use App\Models\NightShift;
use App\Models\SuperappEmployee;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public $selectedDate;
    public $employee;
    public $checkInTime;
    public $checkOutTime;
    public $photo = '';
    public $securityPin = '';
    public $verificationError = '';
    public $draftEntries = [];
    public $completedEntries = [];

    public function mount()
    {
        $this->loadEntries();
    }

    public function loadEntries()
    {
        $query = DraftNightShiftEntry::where('user_id', auth()->id());

        if ($this->selectedDate) {
            $query->whereDate('date', $this->selectedDate);
        }

        $this->draftEntries = $query->orderBy('created_at', 'desc')
            ->get();

        $completedQuery = NightShift::orderBy('created_at', 'desc');

        if ($this->selectedDate) {
            $completedQuery->whereDate('date', $this->selectedDate);
        }

        $this->completedEntries = $completedQuery->take(50)
            ->get();
    }

    public function addEntry()
    {
        $this->validate([
            'selectedDate' => 'required|date',
            'employee' => 'required|string',
            'checkInTime' => 'required|date_format:H:i',
            'checkOutTime' => 'required|date_format:H:i',
        ]);

        // Find employee details
        $employee = SuperappEmployee::where('fullname', $this->employee)->first();

        if (!$employee) {
            $this->addError('employee', 'Employee not found.');
            return;
        }

        DraftNightShiftEntry::create([
            'date' => $this->selectedDate,
            'name' => $employee->fullname,
            'department' => $employee->department->name ?? 'Unknown',
            'check_in_time' => $this->checkInTime,
            'check_out_time' => $this->checkOutTime,
            'photo' => "https://superapp.ewindo.co.id/storage/$employee->photo" ?? '',
            'user_id' => auth()->id(),
        ]);

        $this->reset(['employee', 'checkInTime', 'checkOutTime', 'photo']);
        $this->loadEntries();

        session()->flash('message', 'Entry added to draft successfully.');

        Flux::modal('night-shift-manual-entry')->close();
    }

    public function submitAll()
    {
        $drafts = DraftNightShiftEntry::where('user_id', auth()->id())
            ->whereDate('date', $this->selectedDate)
            ->get();

        foreach ($drafts as $draft) {
            NightShift::create([
                'date' => $draft->date,
                'name' => $draft->name,
                'department' => $draft->department,
                'check_in_time' => $draft->check_in_time,
                'check_out_time' => $draft->check_out_time,
                'photo' => $draft->photo,
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

        Flux::modal('night-shift-submit-verification')->close();
    }

    public function deleteDraft($id)
    {
        DraftNightShiftEntry::find($id)?->delete();
        $this->loadEntries();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex justify-between mb-8">
                <div>
                    <flux:heading>Manual Entry - Night Shift</flux:heading>
                    <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.night-shift') }}" wire:current="dashboard.night-shift" wire:navigate>
                        <flux:icon.arrow-left variant="micro" /> Back to Main Menu
                    </flux:button>
                </div>
            </div>

            {{-- @if (session()->has('message'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    {{ session('message') }}
                </div> --}}
            {{-- @endif --}}

            <div class="grid grid-cols-4 gap-2 items-end">
                <div class="col-span-3">
                    <flux:input wire:model="selectedDate" type="date" wire:change="loadEntries" label="Select Date" class="w-full" />
                </div>
                <div class="col-span-1">
                    <flux:modal.trigger name="night-shift-manual-entry">
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
                        <flux:table.column>Check-In Time</flux:table.column>
                        <flux:table.column>Check-Out Time</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($draftEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->check_in_time }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->check_out_time }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button wire:click="deleteDraft({{ $entry->id }})" variant="ghost" size="sm">
                                        <flux:icon.trash variant="mini" class="text-red-500" />
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-gray-500 italic">
                                    No draft entries found.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="mt-4">
                <flux:modal.trigger name="night-shift-submit-verification">
                    <flux:button variant="primary" :disabled="$draftEntries->count() === 0">Submit All ({{ $draftEntries->count() }})</flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mt-6">
                <flux:heading>Completed Entries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Employees</flux:table.column>
                        <flux:table.column>Department</flux:table.column>
                        <flux:table.column>Check-In Time</flux:table.column>
                        <flux:table.column>Check-Out Time</flux:table.column>
                        <flux:table.column>Confirmed By</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($completedEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->check_in_time }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->check_out_time }}</flux:table.cell>
                                <flux:table.cell>{{ Auth::user()->name }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-gray-500">
                                    No completed entries found.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <flux:modal name="night-shift-manual-entry" class="w-100!">
                <form wire:submit="addEntry">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Add Night Shift Records</flux:heading>
                            <flux:text class="mt-2">Manually enter night shift information for employees.</flux:text>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <div class="autoComplete_wrapper" wire:ignore>
                                    <flux:input id="employee-manual-entry" wire:model="employee" label="Employee Name" placeholder="Select Employee" autocomplete="off" />
                                    @error('employee') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-span-2">
                                <flux:input wire:model="selectedDate" class="col-span-1" label="Date" type="date" />
                                @error('selectedDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <flux:input wire:model="checkInTime" class="col-span-1" label="Check-In Time" type="time" />
                            <flux:input wire:model="checkOutTime" class="col-span-1" label="Check-Out Time" type="time" />
                            @error('checkInTime') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            @error('checkOutTime') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex">
                            <flux:spacer />
                            <div class="flex gap-2 items-end justify-end">
                                <flux:button type="button" x-on:click="$flux.modal('night-shift-manual-entry').close()">Cancel</flux:button>
                                <flux:button type="submit" variant="primary" icon="save">
                                    Save as Draft
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="night-shift-submit-verification" class="w-[1000px]!">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Verify and Submit All Drafts</flux:heading>
                        <flux:text class="mt-2">Review all draft entries and authorize with head of security PIN.</flux:text>
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Employees</flux:table.column>
                            <flux:table.column>Department</flux:table.column>
                            <flux:table.column>Date</flux:table.column>
                            <flux:table.column>Check-In Time</flux:table.column>
                            <flux:table.column>Check-Out Time</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($draftEntries as $entry)
                                <flux:table.row>
                                    <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->formatted_date }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->check_in_time }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->check_out_time }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center text-gray-500">No draft entries available.</flux:table.cell>
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
                        <flux:button type="button" x-on:click="$flux.modal('night-shift-submit-verification').close()">Cancel</flux:button>
                        <flux:button type="button" variant="primary" wire:click="verifyAndSubmit">Verify & Submit</flux:button>
                    </div>
                </div>
            </flux:modal>

        </div>
    </x-pages::dashboard.layout>

</section>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    const autoCompleteJS = new autoComplete({
        selector: "#employee-manual-entry",
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

                    autoCompleteJS.input.value = selection.fullname;
                    $wire.set('employee', selection.fullname);
                }
            }
        }
    });

    window.addEventListener('close-flux-modal', event => {
        const modalName = event.detail?.name;

        if (modalName) {
            document.dispatchEvent(new CustomEvent('modal-closed', {
                detail: { name: modalName },
            }));
        }
    });
</script>