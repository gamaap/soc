<?php

use Livewire\Component;
use App\Models\BoxKey;
use App\Models\KeyBorrowing;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{
	use WithPagination;

    public $vehicle_number;
    public $vehicle_type;
    public $total_keys;
    public $keyId;
    public $borrower_name;
    public $borrower_department;
    public $returned_name;
    public $returned_department;
    public $selectedBorrowingId = '';

    public function save()
    {
        $this->validate([
            'vehicle_number' => 'required|min:3',
            'vehicle_type' => 'required',
            'total_keys' => 'required|numeric|min:0'
        ]);

        BoxKey::create([
            'vehicle_number' => $this->vehicle_number,
            'vehicle_type' => $this->vehicle_type,
            'total_keys' => $this->total_keys,
            'created_by' => auth()->id()
        ]);

        $this->reset();

        Flux::modal('add-box-key')->close();
    }

    public function openBorrowModal($id)
    {
        $this->keyId = $id;

        Flux::modal('borrow-key')->show();
    }

    public function openReturnModal($id)
    {
        $this->keyId = $id;

        $borrowings = KeyBorrowing::where('box_key_id', $id)
            ->whereNull('returned_at')
            ->get();

        $this->selectedBorrowingId = $borrowings->count() === 1 ? $borrowings->first()->id : '';

        Flux::modal('return-key')->show();
    }

    public function borrowKey()
    {
        $this->validate([
            'borrower_name' => 'required|string|min:3',
            'borrower_department' => 'required|string|min:3'
        ]);

        $key = BoxKey::findOrFail($this->keyId);

        if ($key->available <= 0) {
            $this->addError('borrower_name', 'No available keys can be borrowed at the moment.');
            return;
        }

        KeyBorrowing::create([
            'date' => today(),
            'box_key_id' => $this->keyId,
            'borrower_name' => $this->borrower_name,
            'borrower_department' => $this->borrower_department,
            'borrowed_at' => now(),
            'borrow_recorded_by' => auth()->id()
        ]);

        $this->reset('borrower_name','keyId','borrower_department');

        Flux::modal('borrow-key')->close();
    }

    public function returnKey()
    {
        $this->validate([
            'selectedBorrowingId' => 'required|integer',
            'returned_name' => 'required|string|min:3',
            'returned_department' => 'required|string|min:3'
        ]);

        $borrowing = KeyBorrowing::find($this->selectedBorrowingId);

        if (! $borrowing || $borrowing->box_key_id !== $this->keyId || $borrowing->returned_at !== null) {
            $this->addError('selectedBorrowingId', 'Invalid borrowing selected.');
            return;
        }

        $borrowing->update([
            'returned_name' => $this->returned_name,
            'returned_department' => $this->returned_department,
            'returned_at' => now(),
            'return_recorded_by' => auth()->id()
        ]);

        $this->reset('returned_name', 'keyId', 'returned_department', 'selectedBorrowingId');

        Flux::modal('return-key')->close();
    }

    public function showHistory($id)
    {
        $this->keyId = $id;

        Flux::modal('view-box-keys-history')->show();
    }

    #[Computed]
    public function vehicleKeys()
    {
        return BoxKey::with('borrowings')->paginate(10);        
    }

    #[Computed]
    public function histories()
    {
        if (! $this->keyId) {
            return collect();
        }

        $today = today();
        return KeyBorrowing::where('box_key_id', $this->keyId)
            ->where(function($q) use ($today) {
                $q->whereDate('borrowed_at', $today)
                  ->orWhere(function($sub) use ($today) {
                      $sub->whereDate('borrowed_at', '<', $today)->whereNull('returned_at');
                  });
            })
            ->latest()
            ->get();
    }

    #[Computed]
    public function key()
    {
        if (!$this->keyId) {
            return null;
        }

        return BoxKey::with('borrowings')->find($this->keyId);
    }

    #[Computed]
    public function currentBorrow()
    {
        if (! $this->keyId) {
            return null;
        }

        return KeyBorrowing::where('box_key_id', $this->keyId)
            ->whereNull('returned_at')
            ->get();
    }

    #[Computed]
    public function currentBorrower()
    {
        if (! $this->keyId) {
            return null;
        }

        return KeyBorrowing::where('box_key_id', $this->keyId)
            ->whereNull('returned_at')
            ->latest()
            ->first();
    }
};
?>

<x-pages::dashboard.keys>
    <div class="flex justify-between">
        <div>
            <flux:heading>Box Vehicle Keys</flux:heading>
            <flux:text class="mt-2">Manage keys for company box vehicles.</flux:text>
        </div>
        <div class="flex items-center justify-end gap-2">
            <flux:button variant="outline" href="{{ route('dashboard.keys.box-manual-entry') }}" wire:current="dashboard.vehicle-keys-manual-entry" wire:navigate>
                <flux:icon.plus variant="micro" /> Manual Entry
            </flux:button>
            <flux:modal.trigger name="add-box-key">
                <flux:button icon="plus" variant="primary">Add Box Keys</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

     <flux:table class="mt-4" :paginate="$this->vehicleKeys">
        <flux:table.columns>
            <flux:table.column>Vehicle Number</flux:table.column>
            <flux:table.column>Car Type</flux:table.column>
            <flux:table.column>Quantity</flux:table.column>
            <flux:table.column>Available</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->vehicleKeys as $key)
                <flux:table.row>
                    <flux:table.cell>{{ $key->vehicle_number }}</flux:table.cell>
                    <flux:table.cell>{{ $key->vehicle_type }}</flux:table.cell>
                    <flux:table.cell>{{ $key->total_keys }}</flux:table.cell>
                    <flux:table.cell>{{ $key->available }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($key->available === $key->total_keys)
                            <flux:badge size="sm" color="green">Available</flux:badge>
                        @elseif ($key->available === 0)
                            <flux:badge size="sm" color="red">All Borrowed</flux:badge>
                        @else
                            <flux:badge size="sm" color="yellow">Partially Borrowed</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="flex gap-2" class="flex gap-2">
                        @if ($key->available > 0)
                            <flux:button 
                                icon="hand-helping" 
                                size="sm"
                                wire:click="openBorrowModal({{ $key->id }})"
                                wire:target="openBorrowModal({{ $key->id }})"
                            >
                                Borrow
                            </flux:button>
                        @endif

                        @if ($key->available < $key->total_keys)
                            <flux:button 
                                icon="undo-2" 
                                size="sm"
                                variant="primary"
                                wire:click="openReturnModal({{ $key->id }})"
                                wire:target="openReturnModal({{ $key->id }})"
                            >
                                Return
                            </flux:button>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button 
                            icon="eye" 
                            variant="ghost" 
                            size="sm" 
                            wire:click="showHistory({{ $key->id }})"
                            wire:target="showHistory({{ $key->id }})">
                            View
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-6">
                        <flux:text class="text-zinc-400 italic">
                            No vehicle key transaction record available.
                        </flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="add-box-key" class="md:w-96">
        <form wire:submit.prevent="save" action="">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Add Box Key</flux:heading>
                    <flux:text class="mt-2">Register a new vehicle key for tracking.</flux:text>
                </div>
                <flux:input wire:model="vehicle_number" label="Vehicle Number" placeholder="e.g. B 1234 XYZ" />
                <flux:input wire:model="vehicle_type" label="Car Type" placeholder="e.g. Toyota Avanza, Honda CRV" />
                <flux:input wire:model="total_keys" label="Quantity" type="number" placeholder="Number of Keys" />
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary" class="w-full">Add Vehicle Key</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="borrow-key" class="md:w-96">
        <form wire:submit.prevent="borrowKey" action="">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Borrow Key</flux:heading>
                    <flux:text class="mt-2">Record key for borrowing details.</flux:text>
                </div>
                <div class="autoComplete_wrapper" wire:ignore>
                    <flux:input id="borrower-name-vehicle" wire:model="borrower_name" label="Borrower Name" autocomplete="off" />
                </div>
                <flux:input wire:model="borrower_department" autocomplete="off" label="Department" readonly />
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary" class="w-full">Record Borrow</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="return-key" class="md:w-96">
        <form wire:submit.prevent="returnKey" action="">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Return Key</flux:heading>
                    <flux:text class="mt-2">Record key return details.</flux:text>
                </div>

                @if($this->currentBorrow && count($this->currentBorrow) > 1)
                    <div class="bg-blue-500/10 border border-blue-500/20 p-4 rounded-lg">
                        <flux:text class="text-blue-400 font-semibold mb-2">Currently Borrowed Keys</flux:text>
                        @foreach($this->currentBorrow as $borrowing)
                            <div class="text-sm mb-2">
                                <flux:text class="font-medium">{{ $borrowing->borrower_name }}</flux:text>
                                <flux:text class="text-xs text-gray-400">
                                    {{ $borrowing->borrower_department }} · 
                                    {{ \Carbon\Carbon::parse($borrowing->borrowed_at)->format('H:i') }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <flux:label>Select Key to Return</flux:label>
                        <flux:select wire:model="selectedBorrowingId" class="mt-2">
                            <flux:select.option value="">-- Select a key --</flux:select.option>
                            @foreach($this->currentBorrow as $borrowing)
                                <flux:select.option value="{{ $borrowing->id }}">
                                    {{ $borrowing->borrower_name }} ({{ $borrowing->borrower_department }}) - {{ \Carbon\Carbon::parse($borrowing->borrowed_at)->format('H:i') }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('selectedBorrowingId')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                @elseif($this->currentBorrower)
                    <div class="bg-blue-500/10 border border-blue-500/20 p-4 rounded-lg">
                        <flux:text class="text-blue-400 font-semibold mb-2">Currently Borrowed By</flux:text>
                        <flux:text class="font-medium">{{ $this->currentBorrower->borrower_name }}</flux:text>
                        <flux:text class="text-sm text-gray-400">{{ $this->currentBorrower->borrower_department }} - {{ \Carbon\Carbon::parse($this->currentBorrower->borrowed_at)->format('H:i') }}</flux:text>
                        <input type="hidden" wire:model="selectedBorrowingId" value="{{ $this->currentBorrower->id }}">
                        @error('selectedBorrowingId')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                @endif

                <div class="autoComplete_wrapper" wire:ignore>
                    <flux:input wire:model="returned_name" id="returned-name-vehicle" autocomplete="off" label="Return Person Name" />
                </div>
                <flux:input wire:model="returned_department" label="Department" autocomplete="off" />
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary" class="w-full">Record Return</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="view-box-keys-history" class="md:w-200">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Key Transaction History</flux:heading>
                <flux:text class="mt-2">{{ $this->key?->vehicle_number }} - {{ $this->key?->vehicle_type }}</flux:text>
            </div>
            @if($this->currentBorrower)
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-lg">            
                    <flux:text class="text-red-400">Currently Borrowed By</flux:text>
                    
                    <flux:heading size="lg">
                        {{ $this->currentBorrower->borrower_name }}
                    </flux:heading>

                    <flux:text size="sm" variant="strong">
                        Borrowed at {{ \Carbon\Carbon::parse($this->currentBorrower->borrowed_at)->format('H:i') }}
                    </flux:text>
                </div>
            @else
                <div class="bg-green-500/10 border border-green-500/20 p-4 rounded-lg">
                    
                    <flux:text class="text-green-400">Key Status</flux:text>

                    <flux:heading size="lg">
                        All Keys Available
                    </flux:heading>

                </div>
            @endif
            <div class="bg-gray-400/5 p-4 rounded-lg flex flex-row justify-between">
                <div>
                    <flux:text class="mt-2">Total Keys</flux:text>
                    <flux:heading size="xl" class="text-center">
                        {{ $this->key?->total_keys }}
                    </flux:heading>
                </div>
                <div>
                    <flux:text class="mt-2">Currently Borrowed</flux:text>
                    @php
                        $borrowed = $this->key ? $this->key->total_keys - $this->key->available : 0;
                    @endphp
                    <flux:heading size="xl" class="text-center {{ $borrowed > 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ $borrowed }}
                    </flux:heading>
                </div>
                <div>
                    <flux:text class="mt-2">Available</flux:text>
                    <flux:heading size="xl" class="text-center {{ ($this->key?->available ?? 0) > 0 ? 'text-green-500' : 'text-red-500' }}">
                        {{ $this->key?->available }}
                    </flux:heading>
                </div>
            </div>
            <div class="my-4">
                <flux:heading size="lg">Transaction History</flux:heading>
            </div>
            @forelse ($this->histories as $history)
                <div class="p-3 border border-accent rounded-lg">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <flux:heading>Borrower</flux:heading>
                            <flux:text class="mt-2" variant="strong">{{ $history->borrower_name }}</flux:text>
                        </div>
                        <div>
                            <flux:heading>Borrow Department</flux:heading>
                            <flux:text class="mt-2" variant="strong">{{ $history->borrower_department }}</flux:text>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-6 my-4">
                        <div>
                            <flux:heading>Borrow Time</flux:heading>
                            <flux:text class="mt-2" variant="strong">{{ $history->borrowed_at }}</flux:text>
                        </div>
                        <div>
                            <flux:heading>Return Person</flux:heading>
                            <flux:text class="mt-2" variant="strong">{{ $history->returned_name ?? '-' }}</flux:text>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <flux:heading>Return Department</flux:heading>
                            <flux:text class="mt-2" variant="strong">{{ $history->returned_department ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:heading>Return Time</flux:heading>
                            @if ($history->returned_at)
                                <flux:text class="mt-2" variant="strong">
                                    {{ $history->returned_at }}
                                </flux:text>
                            @else
                                <flux:badge color="red" size="sm" class="my-2">Not Returned</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <flux:text class="mt-2 text-center italic">There is no transaction for this key.</flux:text>
            @endforelse
        </div>
    </flux:modal>
</x-pages::dashboard.keys>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    const autoCompleteJSBorrower = new autoComplete({
        selector: "#borrower-name-vehicle",
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
        selector: "#returned-name-vehicle",
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
                    $wire.set('returned_name', selection.fullname);
                    $wire.set('returned_department', selection.department?.name ?? '');
                }
            }
        }
    });
</script>