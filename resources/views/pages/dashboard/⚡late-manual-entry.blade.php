<?php

use App\Models\DraftLateEntry;
use App\Models\Late;
use App\Models\SuperappEmployee;
use Carbon\Carbon;
use Livewire\Component;
use App\Models\SuperappUser;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $selectedDate;
    public $employee;
    public $actualArrivalTime;
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
        $query = DraftLateEntry::where('user_id', auth()->id());

        if ($this->selectedDate) {
            $query->whereDate('date', $this->selectedDate);
        }

        $this->draftEntries = $query->orderBy('created_at', 'desc')
            ->get();

        $completedQuery = Late::orderBy('created_at', 'desc');

        if ($this->selectedDate) {
            $completedQuery->whereDate('date', $this->selectedDate);
        }

        $this->completedEntries = $completedQuery->take(50)
            ->get();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadEntries();
    }

    public function addEntry()
    {
        $this->validate([
            'selectedDate' => 'required|date',
            'employee' => 'required|string',
            'actualArrivalTime' => 'required|date_format:H:i',
        ]);

        // Find employee details
        $employee = SuperappEmployee::where('fullname', $this->employee)->first();

        if (!$employee) {
            $this->addError('employee', 'Employee not found.');
            return;
        }

        $standardArrival = '08:00';
        $actualArrival = $this->actualArrivalTime;
        $minutesLate = $this->calculateMinutesLate($standardArrival, $actualArrival);

        DraftLateEntry::create([
            'date' => $this->selectedDate,
            'name' => $employee->fullname,
            'department' => $employee->department->name ?? 'Unknown',
            'actual_arrival' => $this->actualArrivalTime,
            'minutes_late' => $minutesLate,
            'photo' => "https://superapp.ewindo.co.id/storage/$employee->photo" ?? '',
            'user_id' => auth()->id(),
        ]);

        $this->reset(['employee', 'actualArrivalTime', 'photo']);
        $this->loadEntries();

        session()->flash('message', 'Entry added to draft successfully.');

        Flux::modal('late-manual-entry')->close();
    }

    public function submitAll()
    {
        $drafts = DraftLateEntry::where('user_id', auth()->id())
            ->whereDate('date', $this->selectedDate)
            ->get();

        foreach ($drafts as $draft) {
            Late::create([
                'date' => $draft->date,
                'name' => $draft->name,
                'department' => $draft->department,
                'actual_arrival' => $draft->actual_arrival,
                'minutes_late' => $draft->minutes_late,
                'photo' => $draft->photo,
            ]);

            $draft->delete();
        }

        $this->loadEntries();

        session()->flash('message', 'All draft entries submitted successfully.');
    }

    public function verifyAndSubmit()
    {
        $user = SuperappUser::where('nik', '180702001')->first();

        if (! Hash::check($this->securityPin, $user->pin_code)) {
            $this->verificationError = 'Invalid head of security PIN.';
            return;
        }

        $this->verificationError = '';

        $this->submitAll();

        $this->securityPin = '';

        Flux::modal('late-submit-verification')->close();
    }

    public function deleteDraft($id)
    {
        DraftLateEntry::find($id)?->delete();
        $this->loadEntries();
    }

    private function calculateMinutesLate($standard, $actual)
    {
        $standardTime = strtotime($standard);
        $actualTime = strtotime($actual);

        if ($actualTime <= $standardTime) {
            return 0;
        }

        return round(($actualTime - $standardTime) / 60);
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex justify-between mb-8">
                <div>
                    <flux:heading>Manual Entry - Late Arrival</flux:heading>
                    <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.late') }}" wire:current="dashboard.late" wire:navigate>
                        <flux:icon.arrow-left variant="micro" /> Back to Main Menu
                    </flux:button>
                </div>
            </div>
            
            <div class="grid grid-cols-4 gap-2 items-end">
                <div class="col-span-3">
                    <flux:input wire:model="selectedDate" wire:change="loadEntries" type="date" label="Select Date" class="w-full" />
                </div>
                <div class="col-span-1">
                    <flux:modal.trigger name="late-manual-entry">
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
                        <flux:table.column>Arrival Time</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($draftEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->formatted_time }}</flux:table.cell>
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
                <flux:modal.trigger name="late-submit-verification">
                    <flux:button variant="primary" :disabled="$draftEntries->count() === 0">Submit All ({{ $draftEntries->count() }})</flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mt-6">
                <flux:heading>Completed Entries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Employees</flux:table.column>
                        <flux:table.column>Department</flux:table.column>
                        <flux:table.column>Arrival Time</flux:table.column>
                        <flux:table.column>Confirmed By</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($completedEntries as $entry)
                            <flux:table.row>
                                <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                <flux:table.cell>{{ $entry->formatted_time }}</flux:table.cell>
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

            <flux:modal name="late-manual-entry" class="w-100!">
                <form wire:submit="addEntry">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Add Late Arrival Records</flux:heading>
                            <flux:text class="mt-2">Manually enter late arrival information for employees.</flux:text>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <div class="autoComplete_wrapper" wire:ignore>
                                    <flux:input id="employee-manual-entry" wire:model="employee" label="Employee Name" placeholder="Select Employee" autocomplete="off" />
                                    @error('employee') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <flux:input wire:model="selectedDate" class="col-span-1" label="Date" type="date" />
                            <flux:input wire:model="actualArrivalTime" class="col-span-1" label="Actual Arrival Time" type="time" />
                            @error('selectedDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            @error('actualArrivalTime') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
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

            <flux:modal name="late-submit-verification" class="w-[1000px]!">
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
                            <flux:table.column>Arrival Time</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($draftEntries as $entry)
                                <flux:table.row>
                                    <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->department }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->formatted_date }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->formatted_time }}</flux:table.cell>
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
                        <flux:button type="button" x-on:click="$flux.modal('late-submit-verification').close()">Cancel</flux:button>
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