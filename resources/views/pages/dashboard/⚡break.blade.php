<?php

use Livewire\Component;
use Carbon\Carbon;
use App\Models\Breaks;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Models\SuperappDepartment;
use Livewire\WithPagination;

new class extends Component
{
	use WithPagination;
	
    public $card_number = '';
    public $employee = '';
    public $department = '';
    public $photo = '';
    public $photoLoading = false;
    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

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
            'department' => 'required|string|min:3'
        ]);

        $return = Carbon::now();
        $standard = Carbon::today()->setTime(13,0);

        $minutesLate = 0;

        if ($return->greaterThan($standard)) {
            $minutesLate = floor($standard->diffInMinutes($return));
        }

        Breaks::create([
            'name' => $this->employee,
            'department' => $this->department,
            'actual_return' => $return->format('H:i:s'),
            'minutes_late' => $minutesLate,
            'date' => $return->toDateString(),
            'photo' => $this->photo,
            'created_by' => Auth::id()
        ]);

        $this->reset(['card_number', 'employee', 'department', 'photo']);
    }

    #[Computed]
    public function breaks()
    {
        return Breaks::whereDate('date', today())
            ->where('name', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);
    }
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Record Employee Return Time</flux:heading>
                    <flux:text class="mt-2">Record when employee return from break.</flux:text>
                </div>
                <div>
                    <flux:button variant="outline" href="{{ route('dashboard.break-manual-entry') }}" wire:current="dashboard.break-manual-entry" wire:navigate>
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
                    <form wire:submit.prevent="save" action="" class="grid grid-cols-12 gap-4 items-end flex-1">
                        <div class="col-span-12">
                            <flux:input id="scan-card-break" wire:model.live="card_number" :label="__('System ID')" type="text" placeholder="SCAN HERE" autocomplete="off" autofocus />
                        </div>
                        <div class="col-span-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input wire:model="employee" id="employee-name-break" :label="__('Employee')" type="text" required autocomplete="off" />
                            </div>
                        </div>
                        <div class="col-span-4">
                            <div class="autoComplete_wrapper" wire:ignore>
                                <flux:input wire:model="department" id="department" :label="__('Department')" type="text" autocomplete="off" />
                            </div>
                        </div>
                        <div class="col-span-4">
                            <flux:button variant="primary" class="w-full" type="submit" :disabled="$photoLoading">
                                Record Return Time Now
                            </flux:button>
                        </div>
                    </form>

                    @if (session()->has('error'))
                        <flux:error class="mt-4">{{ session('error') }}</flux:error>
                    @endif
                </div>
            </div>

        </div>

        <div class="border border-accent p-6 rounded-2xl">
            <flux:heading>Break Time History</flux:heading>
            <flux:text class="mt-2">View all break time records.</flux:text>

            <flux:table class="mt-4" :paginate="$this->breaks">
                <div class="my-3 p-2">
                    <flux:input icon="magnifying-glass" placeholder="Search employee name" autocomplete="off" wire:model.live="search" />
                </div>
                <flux:table.columns>
                    <flux:table.column>Photo</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Employee</flux:table.column>
                    <flux:table.column>Department</flux:table.column>
                    {{-- <flux:table.column>Standard Time</flux:table.column> --}}
                    <flux:table.column>Actual Return</flux:table.column>
                    {{-- <flux:table.column>Minutes Late</flux:table.column> --}}
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->breaks as $break)
                        <flux:table.row>
                            <flux:table.cell>
                                <img src="{{ $break->photo ?: asset('img/avatar-default.png') }}" alt="" class="w-15 h-15 rounded-full object-cover border border-accent/20">
                            </flux:table.cell>
                            <flux:table.cell>{{ $break->formatted_date }}</flux:table.cell>
                            <flux:table.cell>{{ $break->name }}</flux:table.cell>
                            <flux:table.cell>{{ $break->department }}</flux:table.cell>
                            {{-- <flux:table.cell>13.00</flux:table.cell> --}}
                            <flux:table.cell>{{ $break->actual_return }}</flux:table.cell>
                            {{-- <flux:table.cell>
                                <flux:badge color="red" size="sm" inset="top bottom">{{ $break->minutes_late }} minutes</flux:badge>
                            </flux:table.cell> --}}
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-6">
                                <flux:text class="text-zinc-400 italic">
                                    No break time record available.
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
    document.getElementById('scan-card-break').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    const autoCompleteJS = new autoComplete({
        selector: "#employee-name-break",
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
                    const resolvedPhoto = selection.photo
                        ? '/proxy/employee-photo?path=' + encodeURIComponent(selection.photo)
                        : '/img/avatar-default.png';

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
                        $wire.set('photo', '/img/avatar-default.png');
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
