<?php

use Livewire\Component;
use App\Models\FacilityKey;
use App\Models\DraftKeyBorrowingsEntry;
use App\Models\KeyBorrowing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    public $selectedDate;
    public $draftEntries = [];
    public $completedEntries = [];
    public $selectedKey;
    public $keys;
    public $borrower_name;
    public $borrower_department;
    public $borrow_time;
    public $return_name;
    public $return_department;
    public $return_time;
    public $securityPin;
    public $verificationError;

    public function mount()
    {
        $this->keys = FacilityKey::all();
        $this->loadDraftEntries();
    }

    public function loadDraftEntries()
    {
        $this->draftEntries = DraftKeyBorrowingsEntry::with('facilityKey')
            ->get()
            ->map(function ($entry) {
                $entry->key_name = $entry->facilityKey->key_name ?? null;
                return $entry;
            });

        $this->completedEntries = KeyBorrowing::orderBy('created_at', 'desc')
            ->whereNotNull('facility_key_id')
            ->take(50)
            ->get();
    }

    public function addEntry()
    {
        $this->validate([
            'selectedKey' => 'required',
            'borrower_name' => 'required',
            'borrower_department' => 'required',
            'borrow_time' => 'required',
            'return_name' => 'nullable',
            'return_department' => 'nullable',
            'return_time' => 'nullable',
        ]);

        DraftKeyBorrowingsEntry::create([
            'vehicle_key_id' => null,
            'facility_key_id' => $this->selectedKey,
            'borrower_name' => $this->borrower_name,
            'borrower_department' => $this->borrower_department,
            'borrowed_at' => Carbon::parse($this->selectedDate . ' ' . $this->borrow_time),
            'returned_name' => $this->return_name,
            'returned_department' => $this->return_department,
            'returned_at' => Carbon::parse($this->selectedDate . ' ' . $this->return_time),
            'borrow_recorded_by' => auth()->id(),
            'return_recorded_by' => auth()->id(),
            'user_id' => auth()->id(),
        ]);
        

        $this->reset(['selectedKey', 'borrower_name', 'borrower_department', 'borrow_time', 'return_name', 'return_department', 'return_time']);
        $this->loadDraftEntries();

        Flux::modal('facility-keys-manual-entry')->close();
    }

    public function deleteDraft($id)
    {
        DB::table('draft_key_borrowings_entries')->where('id', $id)->delete();
        $this->loadDraftEntries();
    }

    public function submitAll()
    {
        $drafts = DraftKeyBorrowingsEntry::where('user_id', auth()->id())->get();

        foreach ($drafts as $draft) {
            KeyBorrowing::create([
                'facility_key_id' => $draft->facility_key_id,
                'borrower_name' => $draft->borrower_name,
                'borrower_department' => $draft->borrower_department,
                'borrowed_at' => Carbon::parse($draft->selected_date . ' ' . $draft->borrow_time),
                'returned_name' => $draft->returned_name,
                'returned_department' => $draft->returned_department,
                'returned_at' => Carbon::parse($draft->selected_date . ' ' . $draft->return_time),
                'borrow_recorded_by' => auth()->id(),
                'return_recorded_by' => auth()->id(),
            ]);

            $draft->delete();
        }

        $this->loadDraftEntries();

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

        Flux::modal('facility-keys-submit-verification')->close();
    }
};
?>

<x-pages::dashboard.keys>
    <div class="flex justify-between mb-8">
        <div>
            <flux:heading>Manual Entry - Facility Keys</flux:heading>
            <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
        </div>
        <div>
            <flux:button variant="outline" href="{{ route('dashboard.keys.facility') }}" wire:current="dashboard.keys.facility" wire:navigate>
                <flux:icon.arrow-left variant="micro" /> Back to Main Menu
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-2 items-end">
        <div class="col-span-3">
            <flux:input wire:model="selectedDate" type="date" label="Select Date" class="w-full" />
        </div>
        <div class="col-span-1">
            <flux:modal.trigger name="facility-keys-manual-entry">
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
                <flux:table.column>Key</flux:table.column>
                <flux:table.column>Borrower Name</flux:table.column>
                <flux:table.column>Borrow Time</flux:table.column>
                <flux:table.column>Return Name</flux:table.column>
                <flux:table.column>Return Time</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($draftEntries as $entry)
                    <flux:table.row>
                        <flux:table.cell>{{ $entry->facilityKey->key_name }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->borrower_name }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->borrowed_at }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->returned_name }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->returned_at }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button wire:click="deleteDraft({{ $entry->id }})" variant="ghost" size="sm">
                                <flux:icon.trash variant="mini" class="text-red-500" />
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-gray-500 italic">
                            No draft entries found.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-4">
        <flux:modal.trigger name="facility-keys-submit-verification">
            <flux:button variant="primary" :disabled="$draftEntries->count() === 0">Submit All ({{ $draftEntries->count() }})</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mt-6">
        <flux:heading>Completed Entries</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Key</flux:table.column>
                <flux:table.column>Borrower Name</flux:table.column>
                <flux:table.column>Borrow Time</flux:table.column>
                <flux:table.column>Return Name</flux:table.column>
                <flux:table.column>Return Time</flux:table.column>
                <flux:table.column>Confirmed By</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($completedEntries as $entry)
                    <flux:table.row>
                        <flux:table.cell>{{ $entry->facilityKey->key_name }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->borrower_name }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->borrowed_at }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->returned_name }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->returned_at }}</flux:table.cell>
                        <flux:table.cell>{{ Auth::user()->name }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center italic text-gray-500">
                            No completed entries found.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="facility-keys-submit-verification" class="w-[1000px]!">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Verify and Submit All Drafts</flux:heading>
                <flux:text class="mt-2">Review all draft entries and authorize with head of security PIN.</flux:text>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Key</flux:table.column>
                    <flux:table.column>Borrower Name</flux:table.column>
                    <flux:table.column>Borrow Time</flux:table.column>
                    <flux:table.column>Return Name</flux:table.column>
                    <flux:table.column>Return Time</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($draftEntries as $entry)
                        <flux:table.row>
                            <flux:table.cell>{{ $entry->facilityKey->key_name }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->borrower_name }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->borrowed_at }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->returned_name }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->returned_at }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-gray-500">No draft entries available.</flux:table.cell>
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
                <flux:button type="button" x-on:click="$flux.modal('facility-keys-submit-verification').close()">Cancel</flux:button>
                <flux:button type="button" variant="primary" wire:click="verifyAndSubmit">Verify & Submit</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="facility-keys-manual-entry" class="md:w-200" :dismissible="false">
        <form wire:submit.prevent="addEntry" action="">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Record Facility Key Entry</flux:heading>
                    <flux:text class="mt-2">Enter facility key information and entry details</flux:text>
                </div>
                <div>
                    <flux:input wire:model="selectedDate" class="col-span-1" label="Date" type="date" />
                </div>
                <div>
                    <flux:select wire:model="selectedKey" class="col-span-1" label="Select Key">
                        <flux:select.option value="">Select a key</flux:select.option>
                        @foreach($keys as $key)
                            <flux:select.option value="{{ $key->id }}">{{ $key->key_name }} - {{ $key->area }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="autoComplete_wrapper" wire:ignore>
                        <flux:input wire:model="borrower_name" id="borrower-name-facility" label="Borrower Name" placeholder="Enter Borrower Name" autocomplete="off" />
                    </div>
                    <flux:input wire:model="borrower_department" label="Borrower Department" placeholder="Enter Borrower Department" autocomplete="off" />
                </div>
                <div>
                    <flux:input wire:model="borrow_time" class="col-span-1" label="Borrow Time" type="time" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="autoComplete_wrapper" wire:ignore>
                        <flux:input wire:model="return_name" id="returned-name-facility" label="Returned Name" placeholder="Enter Returned Name" autocomplete="off" />
                    </div>
                    <flux:input wire:model="return_department" label="Returned Department" placeholder="Enter Returned Department" autocomplete="off" />
                </div>
                <div>
                    <flux:input wire:model="return_time" class="col-span-1" label="Return Time" type="time" />
                </div>
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary" class="w-full">Save as Draft</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</x-pages::dashboard.keys>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    const autoCompleteJSBorrower = new autoComplete({
        selector: "#borrower-name-facility",
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

                    autoCompleteJSBorrower.input.value = selection.fullname;
                    $wire.set('borrower_name', selection.fullname);
                    $wire.set('borrower_department', selection.department?.name ?? '');
                }
            }
        }
    });

    const autoCompleteJSReturned = new autoComplete({
        selector: "#returned-name-facility",
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

                    autoCompleteJSReturned.input.value = selection.fullname;
                    $wire.set('return_name', selection.fullname);
                    $wire.set('return_department', selection.department?.name ?? '');
                }
            }
        }
    });
</script>