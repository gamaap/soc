<?php

use App\Models\DraftDeliveryEntry;
use App\Models\Delivery;
use App\Models\SuperappEmployee;
use Carbon\Carbon;
use Livewire\Component;
use App\Models\SuperappUser;
use Illuminate\Support\Facades\Hash;

new class extends Component
{
    public $items = [];
    public $draftEntries = [];
    public $completedEntries = [];
    public $selectedDate;
    public $name;
    public $company;
    public $visiting;
    public $license_plate;
    public $card_number;
    public $purpose;
    public $entry_time;
    public $exit_time;
    public $securityPin = '';
    public $verificationError = '';

    public function mount()
    {
        $this->items = [
            ['item_name' => '', 'quantity' => '', 'uom' => '']
        ];

        $this->loadEntries();
    }

    public function loadEntries()
    {
        $query = DraftDeliveryEntry::where('user_id', auth()->id());

        if ($this->selectedDate) {
            $query->whereDate('date', $this->selectedDate);
        }

        $this->draftEntries = $query->orderBy('created_at', 'desc')
            ->get();

        $completedQuery = Delivery::orderBy('created_at', 'desc');

        if ($this->selectedDate) {
            $completedQuery->whereDate('date', $this->selectedDate);
        }

        $this->completedEntries = $completedQuery->take(50)
            ->get();
    }

    public function addItem()
    {
        $this->items[] = [
            'item_name' => '',
            'quantity' => '',
            'uom' => '',
        ];
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

        $delivery = DraftDeliveryEntry::create([
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

        foreach ($this->items as $item) {
            $delivery->items()->create([
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'uom' => $item['uom'],
            ]);
        }

        $this->resetForm();
        $this->loadEntries();

        session()->flash('message', 'Entry added to draft successfully.');

        Flux::modal('delivery-manual-entry')->close();
    }

    public function deleteDraft($id)
    {
        DraftDeliveryEntry::find($id)?->delete();
        $this->loadEntries();
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function resetForm()
    {
        $this->reset(['name','company','visiting','license_plate','card_number','purpose', 'entry_time', 'exit_time']);

        $this->items = [
            ['item_name' => '', 'quantity' => '', 'uom' => '']
        ];
    }

    public function submitAll()
    {
       $drafts = DraftDeliveryEntry::where('user_id', auth()->id())
            ->whereDate('date', $this->selectedDate)
            ->get();

        foreach ($drafts as $draft) {
            $delivery = Delivery::create([
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

            foreach ($draft->items as $item) {
                $delivery->items()->create([
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'uom' => $item->uom,
                ]);
            }

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

        Flux::modal('delivery-submit-verification')->close();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex justify-between mb-8">
                <div>
                    <flux:heading>Manual Entry - Delivery</flux:heading>
                    <flux:text class="mt-2">Manage draft and completed entries.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.delivery') }}"
                        wire:current="dashboard.delivery" wire:navigate>
                        <flux:icon.arrow-left variant="micro" /> Back to Main Menu
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2 items-end">
                <div class="col-span-3">
                    <flux:input wire:model="selectedDate" type="date" label="Select Date" wire:change="loadEntries"
                        class="w-full" />
                </div>
                <div class="col-span-1">
                    <flux:modal.trigger name="delivery-manual-entry">
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
                <flux:modal.trigger name="delivery-submit-verification">
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

        <flux:modal name="delivery-manual-entry" class="md:w-200" :dismissible="false">
            <form wire:submit.prevent="addEntry" action="">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Record Delivery Entry</flux:heading>
                        <flux:text class="mt-2">Enter delivery information and delivery details</flux:text>
                    </div>
                    <div>
                        <flux:input wire:model="selectedDate" class="col-span-1" label="Date" type="date" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="name" label="Driver Name" placeholder="Enter Driver Name"
                            autocomplete="off" />
                        <flux:input wire:model="company" label="Company (Optional)" placeholder="Enter Company Name"
                            autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="autoComplete_wrapper" wire:ignore>
                            <flux:input wire:model="visiting" id="delivery-manual-entry" label="Delivery For"
                                placeholder="Person being visited" autocomplete="off" />
                        </div>
                        <flux:input wire:model="license_plate" label="License Plate (Optional)"
                            placeholder="Enter License Plate" autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="card_number" label="Card Number" placeholder="Enter Card Number"
                            autocomplete="off" type="number" />
                    </div>

                <div class="flex items-center justify-between mb-3">
                    <flux:text variant="strong">Items & Quantity</flux:text>
                    <flux:button icon="plus" size="sm" variant="primary" wire:click="addItem">Add Item</flux:button>
                </div>

                @foreach ($items as $index => $item)
                <div class="grid @if ($index > 0) grid-cols-9 @else grid-cols-8 @endif gap-2">
                    <div class="col-span-4">
                        <flux:input wire:model="items.{{ $index }}.item_name" placeholder="Item Name"
                            autocomplete="off" />
                    </div>
                    <div class="col-span-2">
                        <flux:input wire:model="items.{{ $index }}.quantity" placeholder="Quantity"
                            autocomplete="off" />
                    </div>
                    <div class="col-span-2">
                        <flux:select wire:model="items.{{ $index }}.uom" placeholder="Choose...">
                            <flux:select.option>Pcs</flux:select.option>
                            <flux:select.option>Meters</flux:select.option>
                            <flux:select.option>Kgs</flux:select.option>
                            <flux:select.option>Sacks</flux:select.option>
                            <flux:select.option>Kaleng</flux:select.option>
                            <flux:select.option>Liters</flux:select.option>
                            <flux:select.option>Coils</flux:select.option>
                            <flux:select.option>Carries</flux:select.option>
                        </flux:select>
                    </div>
                    @if($index > 0)
                    <div class="ms-2 place-content-center">
                        <flux:button size="sm" variant="danger" wire:click="removeItem({{ $index }})"
                            wire:target="removeItem({{ $index }})">
                            <flux:icon.trash variant="mini" />
                        </flux:button>
                    </div>
                    @endif
                </div>
                @endforeach
                <flux:textarea wire:model="purpose" label="Reason for Delivery"
                    placeholder="Enter the reason for delivery..." />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="entry_time" type="time" label="Entry Time" />
                    <flux:input wire:model="exit_time" type="time" label="Exit Time" />
                </div>
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary" class="w-full">Record Delivery Entry</flux:button>
                </div>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="delivery-submit-verification" class="w-[1000px]!">
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
                    <flux:button type="button" x-on:click="$flux.modal('delivery-submit-verification').close()">Cancel
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
        selector: "#delivery-manual-entry",
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