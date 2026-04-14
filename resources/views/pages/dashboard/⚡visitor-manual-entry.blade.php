<?php

use App\Models\DraftVisitorEntry;
use App\Models\Visitor;
use App\Models\SuperappEmployee;
use Carbon\Carbon;
use Livewire\Component;
use App\Models\SuperappUser;
use Illuminate\Support\Facades\Hash;

new class extends Component
{
    public $selectedDate;
    public $name = '';
    public $company = '';
    public $license_plate = '';
    public $entry_time = '';
    public $exit_time = '';
    public $visiting = '';
    public $card_number;
    public $purpose = '';
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
        $query = DraftVisitorEntry::where('user_id', auth()->id());

        if ($this->selectedDate) {
            $query->whereDate('date', $this->selectedDate);
        }

        $this->draftEntries = $query->orderBy('created_at', 'desc')
            ->get();

        $completedQuery = Visitor::orderBy('created_at', 'desc');

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
            'name' => 'required|string|min:3',
            'company' => 'nullable|min:3',
            'visiting' => 'required|string|min:3',
            'license_plate' => 'nullable|min:3',
            'card_number' => 'nullable|numeric',
            'purpose' => 'nullable|max:500',
            'entry_time' => 'required|date_format:H:i',
            'exit_time' => 'nullable|date_format:H:i|after:entry_time',
        ]);

        DraftVisitorEntry::create([
            'date' => $this->selectedDate,
            'name' => $this->name,
            'company' => $this->company,
            'visiting' => $this->visiting,
            'license_plate' => $this->license_plate,
            'card_number' => $this->card_number,
            'purpose' => $this->purpose,
            'entry_time' => $this->entry_time,
            'exit_time' => $this->exit_time,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name', 'company', 'visiting', 'license_plate', 'card_number', 'purpose', 'entry_time', 'exit_time']);
        $this->loadEntries();

        session()->flash('message', 'Entry added to draft successfully.');

        Flux::modal('visitor-manual-entry')->close();
    }

    public function submitAll()
    {
        $drafts = DraftVisitorEntry::where('user_id', auth()->id())
            ->whereDate('date', $this->selectedDate)
            ->get();

        foreach ($drafts as $draft) {
            Visitor::create([
                'date' => $draft->date,
                'name' => $draft->name,
                'company' => $draft->company,
                'visiting' => $draft->visiting,
                'purpose' => $draft->purpose,
                'license_plate' => $draft->license_plate,
                'card_number' => $draft->card_number,
                'entry_time' => $draft->entry_time,
                'exit_time' => $draft->exit_time,
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

        Flux::modal('visitor-submit-verification')->close();
    }

    public function deleteDraft($id)
    {
        DraftVisitorEntry::find($id)?->delete();
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
                    <flux:heading>Manual Entry - Visitor</flux:heading>
                    <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.visitor') }}"
                        wire:current="dashboard.visitor" wire:navigate>
                        <flux:icon.arrow-left variant="micro" /> Back to Main Menu
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2 items-end">
                <div class="col-span-3">
                    <flux:input wire:model="selectedDate" type="date" wire:change="loadEntries" label="Select Date"
                        class="w-full" />
                </div>
                <div class="col-span-1">
                    <flux:modal.trigger name="visitor-manual-entry">
                        <flux:button variant="primary" class="place-items-end">
                            <flux:icon.plus variant="micro" /> Add Entry for {{ $selectedDate ?
                            Carbon::parse($selectedDate)->format('d/m/Y') : 'Selected Date' }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <div class="mt-6">
                <flux:heading>Draft Entries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Company</flux:table.column>
                        <flux:table.column>Visiting</flux:table.column>
                        <flux:table.column>License Plate</flux:table.column>
                        <flux:table.column>Card Number</flux:table.column>
                        <flux:table.column>Entry Time</flux:table.column>
                        <flux:table.column>Exit Time</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($draftEntries as $entry)
                        <flux:table.row>
                            <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->company }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->visiting }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->card_number }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->entry_time }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->exit_time }}</flux:table.cell>
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
                <flux:modal.trigger name="visitor-submit-verification">
                    <flux:button variant="primary" :disabled="$draftEntries->count() === 0">Submit All ({{
                        $draftEntries->count() }})</flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mt-6">
                <flux:heading>Completed Entries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Company</flux:table.column>
                        <flux:table.column>Visiting</flux:table.column>
                        <flux:table.column>License Plate</flux:table.column>
                        <flux:table.column>Card Number</flux:table.column>
                        <flux:table.column>Entry Time</flux:table.column>
                        <flux:table.column>Exit Time</flux:table.column>
                        <flux:table.column>Confirmed By</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($completedEntries as $entry)
                        <flux:table.row>
                            <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->company }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->visiting }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->card_number }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->entry_time }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->exit_time }}</flux:table.cell>
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

        <flux:modal name="visitor-manual-entry" class="md:w-200" :dismissible="false">
            <form wire:submit.prevent="addEntry" action="">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Record Visitor Entry</flux:heading>
                        <flux:text class="mt-2">Enter visitor information and entry details</flux:text>
                    </div>
                    <div>
                        <flux:input wire:model="selectedDate" class="col-span-1" label="Date" type="date" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="name" label="Visitor Name" placeholder="Enter Visitor Name"
                            autocomplete="off" />
                        <flux:input wire:model="company" label="Company (Optional)" placeholder="Enter Company Name"
                            autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="autoComplete_wrapper" wire:ignore>
                            <flux:input wire:model="visiting" id="visiting-manual-entry" label="Visiting"
                                placeholder="Person being visited" autocomplete="off" />
                        </div>
                        <flux:input wire:model="license_plate" label="License Plate (Optional)"
                            placeholder="Enter License Plate" autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="card_number" label="Card Number" placeholder="Enter Card Number"
                            autocomplete="off" type="number" />
                </div>
                <flux:textarea wire:model="purpose" label="Reason for Visit"
                    placeholder="Enter the reason for visit..." />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="entry_time" type="time" label="Entry Time" />
                    <flux:input wire:model="exit_time" type="time" label="Exit Time" />
                </div>
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary" class="w-full">Record Visitor Entry</flux:button>
                </div>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="visitor-submit-verification" class="w-[1000px]!">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Verify and Submit All Drafts</flux:heading>
                    <flux:text class="mt-2">Review all draft entries and authorize with head of security PIN.
                    </flux:text>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Employees</flux:table.column>
                        <flux:table.column>Company</flux:table.column>
                        <flux:table.column>Visiting</flux:table.column>
                        <flux:table.column>License Plate</flux:table.column>
                        <flux:table.column>Card Number</flux:table.column>
                        <flux:table.column>Entry Time</flux:table.column>
                        <flux:table.column>Exit Time</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($draftEntries as $entry)
                        <flux:table.row>
                            <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->company }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->visiting }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->card_number }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->entry_time }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->exit_time }}</flux:table.cell>
                        </flux:table.row>
                        @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-gray-500">No draft entries available.
                            </flux:table.cell>
                        </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <div>
                    <flux:input wire:model="securityPin" label="Head of Security PIN" type="password"
                        placeholder="Enter 6-digit PIN" />
                    @if($verificationError)
                    <p class="text-sm text-red-600 mt-2">{{ $verificationError }}</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" x-on:click="$flux.modal('visitor-submit-verification').close()">Cancel
                    </flux:button>
                    <flux:button type="button" variant="primary" wire:click="verifyAndSubmit">Verify & Submit
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </x-pages::dashboard.layout>

</section>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    const autoCompleteJS = new autoComplete({
        selector: "#visiting-manual-entry",
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
                    $wire.set('visiting', selection.fullname);
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