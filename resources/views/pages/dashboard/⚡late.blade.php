<?php

use Livewire\Component;
use Carbon\Carbon;
use App\Models\Late;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Models\SuperappDepartment;

new class extends Component
{
    public $card_number = '';
    public $employee = '';
    public $department = '';
    public $photo = '';
    public $photoLoading = false;

    public function updatedCardNumber()
    {
        if (empty($this->card_number)) {
            $this->employee = '';
            $this->department = '';
            $this->photo = '';
            $this->photoLoading = false;
            return;
        }

        $this->photoLoading = true;

        $rfidEmployee = DB::connection('rfid')->table('employees')->where('card_number', $this->card_number)->first();

        if ($rfidEmployee) {
            $this->employee = $rfidEmployee->name;
            $department = SuperappDepartment::find($rfidEmployee->department_id);
            $this->department = $department ? $department->name : '';
            $this->photo = $rfidEmployee->photo ?: '';
        } else {
            $this->employee = '';
            $this->department = '';
            $this->photo = '';

            session()->flash('error', 'Employee not found for this card number.');
        }

        $this->photoLoading = false;
    }

    public function save()
    {
        $validated = $this->validate([
            'employee' => 'required|string|min:3|max:255',
            'department' => 'nullable|string|min:3'
        ]);

        $arrival = Carbon::now();
        $standard = Carbon::today()->setTime(8,0);

        $minutesLate = 0;

        if ($arrival->greaterThan($standard)) {
            $minutesLate = floor($standard->diffInMinutes($arrival));
        }

        Late::create([
            'name' => $this->employee,
            'department' => $this->department,
            'actual_arrival' => $arrival->format('H:i:s'),
            'minutes_late' => $minutesLate,
            'date' => $arrival->toDateString(),
            'photo' => $this->photo,
            'created_by' => Auth::id()
        ]);

        $this->reset(['card_number', 'employee', 'department', 'photo']);
    }

    #[Computed]
    public function lates()
    {
        return Late::whereDate('date', today())->latest()->paginate(10);
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex justify-between">
                <div>
                    <flux:heading>Record Late Arrival</flux:heading>
                    <flux:text class="mt-2">Record employees who arrive late to the facility.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.late-manual-entry') }}" wire:current="dashboard.late-manual-entry" wire:navigate>
                        <flux:icon.plus variant="micro" /> Manual Entry
                    </flux:button>
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-6 items-center mt-4">
                <div class="relative flex-1 md:w-1/2">
                    <img
                        src="{{ $photo ?: asset('img/avatar-default.png') }}"
                        onerror="this.onerror=null; this.src='https://placehold.co/300';"
                        alt="Employee photo"
                        class="w-full h-auto rounded-xl object-cover border border-accent/20"
                    >

                    @if ($photoLoading)
                        <div class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-xl">
                            <span class="text-sm font-bold">
                                <flux:icon.loading />
                            </span>
                        </div>
                    @endif
                </div>

                <div class="w-full md:w-1/2 flex-6 flex-col gap-4">
                    <form wire:submit.prevent="save" class="grid grid-cols-12 gap-4 items-end flex-1">
                        <div class="col-span-12">
                            <flux:input id="scan-card-late" wire:model.live="card_number" :label="__('System ID')" type="text" placeholder="SCAN HERE" autocomplete="off" autofocus />
                        </div>
                        <div class="col-span-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input id="employee-name-late" wire:model="employee" :label="__('Employee')" type="text" autocomplete="off" />
                            </div>
                        </div>
                        <div class="col-span-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input id="department" wire:model="department" :label="__('Department')" type="text" autocomplete="off" />
                            </div>
                        </div>
                        <div class="col-span-4">
                            <flux:button variant="primary" class="w-full" type="submit" :disabled="$photoLoading">
                                Record Arrival Time
                            </flux:button>
                        </div>
                    </form>

                    @if(session('error'))
                        <flux:error class="mt-4">{{ session('error') }}</flux:error>
                    @endif
                </div>
            </div>
        </div>

        <div class="border border-accent p-6 rounded-2xl">
            <flux:heading>Late Arrival Records</flux:heading>
            <flux:text class="mt-2">History of late arrivals.</flux:text>

            <flux:table class="mt-4" :paginate="$this->lates">
                <flux:table.columns>
                    <flux:table.column>Photo</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Employee</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    {{-- <flux:table.column>Standard Time</flux:table.column> --}}
                    <flux:table.column>Actual Arrival</flux:table.column>
                    {{-- <flux:table.column>Minutes Late</flux:table.column> --}}
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->lates as $late)
                        <flux:table.row>
                            <flux:table.cell>
                                <img src="{{ $late->photo ?: asset('img/avatar-default.png') }}" alt="" class="w-15 h-15 rounded-full object-cover border border-accent/20">
                            </flux:table.cell>
                            <flux:table.cell>{{ $late->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $late->name }}</flux:table.cell>
                            <flux:table.cell>{{ $late->department }}</flux:table.cell>
                            {{-- <flux:table.cell>08.00</flux:table.cell> --}}
                            <flux:table.cell>{{ $late->formatted_time }}</flux:table.cell>
                            {{-- <flux:table.cell>
                                <flux:badge color="red" size="sm" inset="top bottom">{{ $late->minutes_late }} minutes</flux:badge>
                            </flux:table.cell> --}}
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No late arrival record available.
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::dashboard.layout>
</section>

<script src="https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@10.2.9/dist/autoComplete.min.js"></script>
<script>
    // Prevent form submission on Enter key for scan-card input
    document.getElementById('scan-card-late').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    const autoCompleteJS = new autoComplete({
        selector: "#employee-name-late",
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
                    const resolvedPhoto = selection.photo ? 'https://superapp.ewindo.co.id/storage/' + selection.photo : 'https://placehold.co/300';

                    autoCompleteJS.input.value = selection.fullname;
                    $wire.set('employee', selection.fullname);
                    $wire.set('department', selection.department?.name ?? '');

                    // Show loader until the image loads/fails
                    $wire.set('photoLoading', true);

                    const img = new Image();
                    img.onload = () => {
                        $wire.set('photo', resolvedPhoto);
                        $wire.set('photoLoading', false);
                    };
                    img.onerror = () => {
                        $wire.set('photo', 'https://placehold.co/300');
                        $wire.set('photoLoading', false);
                    };
                    img.src = resolvedPhoto;
                }
            }
        }
    });

    const department = new autoComplete({
        selector: "#department",
        data: {
            src: async (query) => {
                try {
                    const source = await fetch(`/departments/api?search=${encodeURIComponent(query)}`);
                    const data = await source.json();

                    return data;
                } catch (error) {
                    return error;
                }
            },
            keys: ["name"],
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

                    $wire.set('department', selection.name);
                }
            }
        }
    });
</script>
