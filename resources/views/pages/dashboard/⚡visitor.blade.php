<?php

use Livewire\Component;
use Carbon\Carbon;
use App\Models\Visitor;
use Livewire\Attributes\Computed;
use Flux\Flux;
use Livewire\WithPagination;

new class extends Component
{
	use WithPagination;
	
    public $name;
    public $company;
    public $visiting;
    public $license_plate;
    public $card_number;
    public $purpose;
    public $visitorId = null;
    public $exitVisitorId = null;
    public $exitHasItems = false;
    public $exitItems = [];
    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string',
            'company' => 'nullable|min:3',
            'visiting' => 'required|string|min:3',
            'license_plate' => 'nullable|min:3',
            'purpose' => 'required|max:500',
        ]);

        $now = Carbon::now();

        Visitor::create([
            'name' => $this->name,
            'company' => $this->company,
            'visiting' => $this->visiting,
            'license_plate' => $this->license_plate,
            'card_number' => $this->card_number,
            'purpose' => $this->purpose,
            'date' => $now->toDateString(),
            'entry_time' => $now->format('H:i:s'),
            'created_by' => auth()->id(),
        ]);

        $this->reset();

        Flux::modal('record-visitor')->close();
    }

    public function openExitModal($id)
    {
        $this->exitVisitorId = $id;
        $this->exitHasItems = false;
        $this->exitItems = [
            ['item_name' => '', 'quantity' => '', 'uom' => '']
        ];

        Flux::modal('record-exit')->show();
    }

    public function addExitItem()
    {
        $this->exitItems[] = [
            'item_name' => '',
            'quantity' => '',
            'uom' => '',
        ];
    }

    public function removeExitItem($index)
    {
        unset($this->exitItems[$index]);
        $this->exitItems = array_values($this->exitItems);
    }

    public function confirmExit()
    {
        $visitor = Visitor::findOrFail($this->exitVisitorId);

        $visitor->update([
            'exit_time' => Carbon::now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);

        if ($this->exitHasItems) {
            foreach ($this->exitItems as $item) {
                if (blank($item['item_name'])) {
                    continue;
                }

                $visitor->items()->create([
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'] ?: 0,
                    'uom' => $item['uom'],
                ]);
            }
        }

        $this->reset(['exitVisitorId', 'exitHasItems', 'exitItems']);

        Flux::modal('record-exit')->close();
    }

    #[Computed]
    public function visitors()
    {
        $today = today();
        return Visitor::where(function($q) use ($today) {
            $q->whereDate('date', $today)
              ->orWhere(function($sub) use ($today) {
                  $sub->whereDate('date', '<', $today)->whereNull('exit_time');
              });
        })
        ->where('name', 'like', '%' . $this->search . '%')
        ->orderByRaw('exit_time IS NULL DESC, exit_time ASC')->latest()->paginate(10);
    }

    #[Computed]
    public function visitor()
    {
        if (! $this->visitorId) {
            return null;
        }

        return Visitor::with('items')->find($this->visitorId);
    }

    public function showVisitor($id)
    {
        $this->visitorId = $id;

        Flux::modal('view-visitor')->show();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Visitor Records</flux:heading>
                    <flux:text class="mt-2">Track visitor entries and exits.</flux:text>
                </div>

                <div class="flex justify-end gap-x-2">
                    <flux:button variant="outline" href="{{ route('dashboard.visitor-manual-entry') }}" wire:current="dashboard.visitor-manual-entry" wire:navigate>
                        <flux:icon.plus variant="micro" /> Manual Entry
                    </flux:button>
                    <flux:modal.trigger name="record-visitor">
                        <flux:button icon="users">Record Visitor Entry</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <flux:table class="mt-4" :paginate="$this->visitors">
                <div class="my-3 p-2">
                    <flux:input icon="magnifying-glass" placeholder="Search visitor name" autocomplete="off" wire:model.live="search" />
                </div>
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Company</flux:table.column>
                    <flux:table.column>Visiting</flux:table.column>
                    <flux:table.column>License Plate</flux:table.column>
                    <flux:table.column>Card Number</flux:table.column>
                    <flux:table.column>Entry Time</flux:table.column>
                    <flux:table.column>Exit Time</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->visitors as $visitor)
                        <flux:table.row>
                            <flux:table.cell>{{ $visitor->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $visitor->name }}</flux:table.cell>
                            <flux:table.cell>{{ $visitor->company ?? '-'}}</flux:table.cell>
                            <flux:table.cell>{{ $visitor->visiting }}</flux:table.cell>
                            <flux:table.cell>{{ $visitor->license_plate ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $visitor->card_number ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $visitor->entry_time }}</flux:table.cell>
                            <flux:table.cell>
                                @if (! $visitor->exit_time)
                                    <flux:button
                                    wire:click="openExitModal({{ $visitor->id }})"
                                    wire:target="openExitModal({{ $visitor->id }})"
                                    >
                                        Record Exit
                                    </flux:button>
                                @else
                                    {{ $visitor->exit_time }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button 
                                    icon="eye" 
                                    variant="ghost" 
                                    size="sm" 
                                    wire:click="showVisitor({{ $visitor->id }})"
                                    wire:target="showVisitor({{ $visitor->id }})">
                                    View
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No visitor record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <flux:modal name="record-visitor" class="md:w-200" :dismissible="false">
                <form wire:submit.prevent="save" action="">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Record Visitor Entry</flux:heading>
                            <flux:text class="mt-2">Enter visitor information and entry details</flux:text>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="name" label="Visitor Name" placeholder="Enter Visitor Name" autocomplete="off" />
                            <flux:input wire:model="company" label="Company (Optional)" placeholder="Enter Company Name" autocomplete="off" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input wire:model="visiting" id="visiting" label="Visiting" placeholder="Person being visited" autocomplete="off" />
                            </div>
                            <flux:input wire:model="license_plate" label="License Plate (Optional)" placeholder="Enter License Plate" autocomplete="off" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="card_number" type="number" label="Card Number" placeholder="Enter Card Number" autocomplete="off" />
                        </div>
                        <flux:textarea
                            wire:model="purpose"
                            label="Reason for Visit"
                            placeholder="Enter the reason for visit..."
                        />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary" class="w-full">Record Visitor Entry</flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="view-visitor" class="md:w-200">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Visitor Details</flux:heading>
                        <flux:text class="mt-2">Complete information about the visitor</flux:text>
                    </div>
                    @if ($this->visitor)
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Visitor Name</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->name }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Company</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->company }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Visiting Person</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->visiting }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>License Plate</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->license_plate }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Entry Time</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->entry_time }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Card Number</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->card_number }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Exit Time</flux:heading>
                                @if ($this->visitor->exit_time)
                                    <flux:text class="mt-2" variant="strong">
                                        {{ $this->visitor->exit_time }}
                                    </flux:text>
                                @else
                                    <flux:badge color="red" size="sm" class="my-2">Exit Not Recorded</flux:badge>
                                @endif
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Visit Date</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->formatted_date }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Reason for Visit</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->purpose }}</flux:text>
                            </div>
                        </div>

                        @if ($this->visitor->items->isNotEmpty())
                            <flux:separator variant="subtle" />

                            <flux:heading size="lg">Items Taken Out</flux:heading>

                            @foreach ($this->visitor->items as $item)
                                <div class="flex items-center justify-between bg-gray-400/5 rounded-xl p-2 my-2">
                                    <flux:text variant="strong">{{ $item->item_name }}</flux:text>
                                    <flux:text variant="strong">{{ $item->quantity }} {{ $item->uom }}</flux:text>
                                </div>
                            @endforeach
                        @endif

                        <flux:separator variant="subtle" />

                        <flux:heading size="lg">Record Information</flux:heading>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Recorded By</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ auth()->user()->name }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Recorded At</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->visitor->created_at }}</flux:text>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:modal>

            <flux:modal name="record-exit" class="md:w-200" :dismissible="false">
                <form wire:submit.prevent="confirmExit" action="">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Record Visitor Exit</flux:heading>
                            <flux:text class="mt-2">Confirm exit time and check if the visitor is taking items out.</flux:text>
                        </div>

                        <flux:radio.group wire:model.live="exitHasItems" label="Apakah tamu membawa barang keluar?">
                            <flux:radio value="1" label="Ya" />
                            <flux:radio value="0" label="Tidak" />
                        </flux:radio.group>

                        @if ($exitHasItems)
                            <div class="flex items-center justify-between mb-3">
                                <flux:text variant="strong">Items Taken Out</flux:text>
                                <flux:button icon="plus" size="sm" variant="primary" wire:click="addExitItem">Add Item</flux:button>
                            </div>

                            @foreach ($exitItems as $index => $item)
                                <div class="grid @if ($index > 0) grid-cols-9 @else grid-cols-8 @endif gap-2">
                                    <div class="col-span-4">
                                        <flux:input wire:model="exitItems.{{ $index }}.item_name" placeholder="Item Name" autocomplete="off" />
                                    </div>
                                    <div class="col-span-2">
                                        <flux:input wire:model="exitItems.{{ $index }}.quantity" placeholder="Quantity" autocomplete="off" />
                                    </div>
                                    <div class="col-span-2">
                                        <flux:select wire:model="exitItems.{{ $index }}.uom" placeholder="Choose...">
                                            <flux:select.option>Pcs</flux:select.option>
                                            <flux:select.option>Meters</flux:select.option>
                                            <flux:select.option>Kgs</flux:select.option>
                                            <flux:select.option>Sacks</flux:select.option>
                                            <flux:select.option>Kaleng</flux:select.option>
                                            <flux:select.option>Liters</flux:select.option>
                                            <flux:select.option>Coils</flux:select.option>
                                            <flux:select.option>Carries</flux:select.option>
                                            <flux:select.option>Box</flux:select.option>
                                            <flux:select.option>Bucket</flux:select.option>
                                        </flux:select>
                                    </div>
                                    @if($index > 0)
                                        <div class="ms-2 place-content-center">
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="removeExitItem({{ $index }})"
                                                wire:target="removeExitItem({{ $index }})"
                                            >
                                                <flux:icon.trash variant="mini" />
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endif

                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary" class="w-full">Record Exit</flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>

        </div>
    </x-pages::dashboard.layout>
</section>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    const autoCompleteJS = new autoComplete({
        selector: "#visiting",
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
</script>