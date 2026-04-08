<?php

use Livewire\Component;
use App\Models\Delivery;
use Flux\Flux;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $items = [];
    public $name;
    public $company;
    public $visiting;
    public $license_plate;
    public $purpose;
    public $deliveryId = null;

    public function mount()
    {
        $this->items = [
            ['item_name' => '', 'quantity' => '', 'uom' => '']
        ];
    }

    public function addItem()
    {
        $this->items[] = [
            'item_name' => '',
            'quantity' => '',
            'uom' => '',
        ];
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function resetForm()
    {
        $this->reset(['name','company','visiting','license_plate','purpose']);

        $this->items = [
            ['item_name' => '', 'quantity' => '', 'uom' => '']
        ];
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|min:3',
            'company' => 'nullable|min:3',
            'visiting' => 'required|string|min:3',
            'license_plate' => 'nullable|min:3',
            'purpose' => 'nullable|max:500',
        ]);

        $now = Carbon::now();

        $delivery = Delivery::create([
            'name' => $this->name,
            'company' => $this->company,
            'visiting' => $this->visiting,
            'license_plate' => $this->license_plate,
            'purpose' => $this->purpose,
            'date' => $now->toDateString(),
            'entry_time' => $now->format('H:i:s'),
            'created_by' => auth()->id(),
        ]);

        foreach ($this->items as $item) {
            $delivery->items()->create([
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'uom' => $item['uom'],
            ]);
        }

        $this->resetForm();

        Flux::modal('record-delivery')->close();
    }

    public function recordExit($id)
    {
        $delivery = Delivery::findOrFail($id);

        $delivery->update([
            'exit_time' => Carbon::now()->format('H:i:s'),
            'updated_by' => auth()->id(),
        ]);
    }

    #[Computed]
    public function deliveries()
    {
        $today = today();
        return Delivery::with('items')->where(function($q) use ($today) {
            $q->whereDate('date', $today)
              ->orWhere(function($sub) use ($today) {
                  $sub->whereDate('date', '<', $today)->whereNull('exit_time');
              });
        })->orderByRaw('exit_time IS NULL DESC, exit_time ASC')->latest()->paginate(10);
    }

    #[Computed]
    public function delivery()
    {
        if (! $this->deliveryId) {
            return null;
        }

        return Delivery::find($this->deliveryId);
    }

    public function showDelivery($id)
    {
        $this->deliveryId = $id;

        Flux::modal('view-delivery')->show();
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Delivery Records</flux:heading>
                    <flux:text class="mt-2">Track delivery entries and exits.</flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="outline" href="{{ route('dashboard.delivery-manual-entry') }}" wire:current="dashboard.delivery-manual-entry" wire:navigate>
                        <flux:icon.plus variant="micro" /> Manual Entry
                    </flux:button>
                    <flux:modal.trigger name="record-delivery">
                        <flux:button icon="truck">Record Delivery Entry</flux:button>
                    </flux:modal.trigger>
                </div>

            </div>

            <flux:table class="mt-4" :paginate="$this->deliveries">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Company</flux:table.column>
                    <flux:table.column>Visiting</flux:table.column>
                    <flux:table.column>License Plate</flux:table.column>
                    <flux:table.column>Entry Time</flux:table.column>
                    <flux:table.column>Exit Time</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->deliveries as $delivery)
                        <flux:table.row>
                            <flux:table.cell>{{ $delivery->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $delivery->name }}</flux:table.cell>
                            <flux:table.cell>{{ $delivery->company ?? '-'}}</flux:table.cell>
                            <flux:table.cell>{{ $delivery->visiting }}</flux:table.cell>
                            <flux:table.cell>{{ $delivery->license_plate ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $delivery->entry_time }}</flux:table.cell>
                            <flux:table.cell>
                                @if (! $delivery->exit_time)
                                    <flux:button 
                                    wire:click="recordExit({{ $delivery->id }})"
                                    wire:target="recordExit({{ $delivery->id }})"
                                    >
                                        Record Exit
                                    </flux:button> 
                                @else
                                    {{ $delivery->exit_time }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button 
                                    icon="eye" 
                                    variant="ghost" 
                                    size="sm" 
                                    wire:click="showDelivery({{ $delivery->id }})"
                                    wire:target="showDelivery({{ $delivery->id }})">
                                    View
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No delivery record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <flux:modal name="view-delivery" class="md:w-200">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Delivery Details</flux:heading>
                        <flux:text class="mt-2">Complete information about the delivery</flux:text>
                    </div>
                    @if ($this->delivery)
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Driver Name</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->delivery->name }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Company</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->delivery->company ?? '-' }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Visiting Person</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->delivery->visiting }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>License Plate</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->delivery->license_plate ?? '-' }}</flux:text>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Entry Time</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->delivery->entry_time }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Exit Time</flux:heading>
                                @if ($this->delivery->exit_time)
                                    <flux:text class="mt-2" variant="strong">
                                        {{ $this->delivery->exit_time }}
                                    </flux:text>
                                @else
                                    <flux:badge color="red" size="sm" class="my-2">Exit Not Recorded</flux:badge>
                                @endif
                            </div>
                        </div>

                        <flux:separator variant="subtle" />

                        <flux:heading size="lg">Items & Quantity</flux:heading>

                        @foreach ($this->delivery->items as $item)
                            <div class="flex items-center justify-between bg-gray-400/5 rounded-xl p-2 my-2">
                                <flux:text variant="strong">{{ $item->item_name }}</flux:text>
                                <flux:text variant="strong">{{ $item->quantity }} {{ $item->uom }}</flux:text>
                            </div>
                        @endforeach

                        <flux:separator variant="subtle" />

                        <flux:heading size="lg">Record Information</flux:heading>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <flux:heading>Recorded By</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ auth()->user()->name }}</flux:text>
                            </div>
                            <div>
                                <flux:heading>Recorded At</flux:heading>
                                <flux:text class="mt-2" variant="strong">{{ $this->delivery->created_at }}</flux:text>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:modal>

            <flux:modal name="record-delivery" class="md:w-200" :dismissible="false">
                <form wire:submit.prevent="save" action="">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Record Delivery Entry</flux:heading>
                            <flux:text class="mt-2">Enter delivery information and delivery details</flux:text>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="name" label="Driver Name" placeholder="Enter Driver Name" autocomplete="off" />
                            <flux:input wire:model="company" label="Company (Optional)" placeholder="Enter Company Name" autocomplete="off" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input wire:model="visiting" id="delivery-for" label="Delivery For" placeholder="Person being visited" autocomplete="off" />
                            </div>
                            <flux:input wire:model="license_plate" label="License Plate (Optional)" placeholder="Enter License Plate" autocomplete="off" />
                        </div>
                        
                        <div class="flex items-center justify-between mb-3">
                            <flux:text variant="strong">Items & Quantity</flux:text>
                            <flux:button icon="plus" size="sm" variant="primary" wire:click="addItem">Add Item</flux:button>
                        </div>

                        @foreach ($items as $index => $item)
                            <div class="grid @if ($index > 0) grid-cols-9 @else grid-cols-8 @endif gap-2">
                                <div class="col-span-4">
                                    <flux:input wire:model="items.{{ $index }}.item_name" placeholder="Item Name" autocomplete="off" />
                                </div>
                                <div class="col-span-2">
                                    <flux:input wire:model="items.{{ $index }}.quantity" placeholder="Quantity" autocomplete="off" />
                                </div>
                                <div class="col-span-2">
                                    <flux:select wire:model="items.{{ $index }}.uom" placeholder="Choose...">
                                        <flux:select.option>Pcs</flux:select.option>
                                        <flux:select.option>Meters</flux:select.option>
                                        <flux:select.option>Kgs</flux:select.option>
                                    </flux:select>
                                </div>
                                @if($index > 0)
                                    <div class="ms-2 place-content-center">
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="removeItem({{ $index }})"
                                            wire:target="removeItem({{ $index }})"
                                        >
                                            <flux:icon.trash variant="mini" />
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        <flux:textarea
                            wire:model="purpose"
                            label="Reason for Delivery"
                            placeholder="Enter the reason for delivery..."
                        />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary" class="w-full">Record Visitor Entry</flux:button>
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
        selector: "#delivery-for",
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