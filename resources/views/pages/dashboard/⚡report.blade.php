<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Models\Late;
use App\Models\Breaks;
use App\Models\NightShift;
use App\Models\Visitor;
use App\Models\Delivery;
use App\Models\VehiclePass;
use App\Models\KeyBorrowing;
use App\Models\SuperappDepartment;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $category = '';
    public $department = '';
    public $month = '';
    public $dateFrom = '';
    public $dateTo = '';

    #[Computed]
    public function categoryCounts()
    {
        return [
            'late' => Late::count(),
            'break' => Breaks::count(),
            'night_shift' => NightShift::count(),
            'visitor' => Visitor::count(),
            'delivery' => Delivery::count(),
            'vehicle_pass' => VehiclePass::count(),
            'keys' => KeyBorrowing::count(),
        ];
    }

    #[Computed]
    public function departments()
    {
        return SuperappDepartment::orderBy('name')->get();
    }

    public function mount()
    {
        $this->month = now()->format('Y-m');
    }

    public function records()
    {
        $query = match($this->category) {
            'late' => $this->getLateRecords(),
            'break' => $this->getBreakRecords(),
            'night_shift' => $this->getNightShiftRecords(),
            'visitor' => $this->getVisitorRecords(),
            'delivery' => $this->getDeliveryRecords(),
            'vehicle_pass' => $this->getVehiclePassRecords(),
            'keys' => $this->getKeyRecords(),
            default => collect([]),
        };

        return $query;
    }

    public function columns()
    {
        return match($this->category) {
            'late' => ['Date', 'Name', 'Department', 'Actual Arrival', 'Minutes Late'],
            'break' => ['Date', 'Name', 'Department', 'Actual Return', 'Minutes Late'],
            'night_shift' => ['Date', 'Name', 'Department', 'Check-in Time', 'Check-out Time'],
            'visitor' => ['Date', 'Name', 'Company', 'Visiting', 'License Plate', 'Card Number', 'Entry Time', 'Exit Time'],
            'delivery' => ['Date', 'Name', 'Company', 'Visiting', 'License Plate', 'Card Number', 'Entry Time', 'Exit Time'],
            'vehicle_pass' => ['Date', 'Name', 'License Plate', 'Purpose', 'Destination', 'Starting KM', 'Ending KM', 'Leaving Time', 'Return Time'],
            'keys' => ['Date', 'Key', 'Borrower Name', 'Department', 'Borrowed At', 'Returned At'],
            default => [],
        };
    }

    #[Computed]
    public function hasRecords()
    {
        return match($this->category) {
            'late' => Late::count() > 0,
            'break' => Breaks::count() > 0,
            'night_shift' => NightShift::count() > 0,
            'visitor' => Visitor::count() > 0,
            'delivery' => Delivery::count() > 0,
            'vehicle_pass' => VehiclePass::count() > 0,
            'keys' => KeyBorrowing::count() > 0,
            default => false,
        };
    }

    private function applyCommonFilters($query, $applyDepartment = true)
    {
        if ($this->dateFrom) {
            $query->whereDate('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('date', '<=', $this->dateTo);
        }

        if ($this->month) {
            $query->whereYear('date', substr($this->month, 0, 4))
                  ->whereMonth('date', substr($this->month, 5, 2));
        }

        if ($applyDepartment && $this->department) {
            $query->where('department', $this->department);
        }

        return $query;
    }

    private function getLateRecords()
    {
        $query = Late::query();
        $this->applyCommonFilters($query);
        return $query->orderBy('date', 'desc')
            ->orderBy('actual_arrival', 'desc')
            ->get();
    }

    private function getBreakRecords()
    {
        $query = Breaks::query();
        $this->applyCommonFilters($query);
        return $query->orderBy('date', 'desc')
            ->orderBy('actual_return', 'desc')
            ->get();
    }

    private function getNightShiftRecords()
    {
        $query = NightShift::query();
        $this->applyCommonFilters($query);
        return $query->orderBy('date', 'desc')
            ->orderBy('check_in_time', 'desc')
            ->get();
    }

    private function getVisitorRecords()
    {
        $query = Visitor::query();
        $this->applyCommonFilters($query, false);
        return $query->orderBy('date', 'desc')
            ->orderBy('entry_time', 'desc')
            ->get();
    }

    private function getDeliveryRecords()
    {
        $query = Delivery::query();
        $this->applyCommonFilters($query, false);
        return $query->orderBy('date', 'desc')
            ->orderBy('entry_time', 'desc')
            ->get();
    }

    private function getVehiclePassRecords()
    {
        $query = VehiclePass::query();
        $this->applyCommonFilters($query, false);
        return $query->orderBy('date', 'desc')
            ->orderBy('leaving_time', 'desc')
            ->get();
    }

    private function getKeyRecords()
    {
        $query = KeyBorrowing::query()
            ->with(['vehicleKey', 'facilityKey', 'boxKey']);
        
        if ($this->dateFrom) {
            $query->whereDate('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('date', '<=', $this->dateTo);
        }
        if ($this->month) {
            $query->whereYear('date', substr($this->month, 0, 4))
                  ->whereMonth('date', substr($this->month, 5, 2));
        }
        if ($this->department) {
            $query->where('borrower_department', $this->department);
        }
        
        return $query->orderBy('date', 'desc')
            ->orderBy('borrowed_at', 'desc')
            ->get();
    }

    public function export()
    {
        $records = $this->records();
        $category = $this->category;
        
        if ($records->isEmpty()) {
            session()->flash('error', 'No records to export.');
            return;
        }

        $filename = 'report_' . ($category ?: 'all') . '_' . now()->format('Y-m-d') . '.csv';

        // Dynamic headers based on category
        $headers = $this->columns();

        $callback = function() use ($records, $headers, $category) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($records as $record) {
                $row = match($category) {
                    'late' => [
                        $record->formatted_date,
                        $record->name,
                        $record->department,
                        $record->formatted_time,
                        $record->minutes_late,
                    ],
                    'break' => [
                        $record->formatted_date,
                        $record->name,
                        $record->department,
                        $record->actual_return,
                        $record->minutes_late,
                    ],
                    'night_shift' => [
                        $record->formatted_date,
                        $record->name,
                        $record->department,
                        $record->check_in_time,
                        $record->check_out_time,
                    ],
                    'visitor' => [
                        $record->formatted_date,
                        $record->name,
                        $record->company ?? '-',
                        $record->visiting,
                        $record->license_plate ?? '-',
                        $record->card_number ?? '-',
                        $record->entry_time,
                        $record->exit_time ?? '-',
                    ],
                    'delivery' => [
                        $record->formatted_date,
                        $record->name,
                        $record->company ?? '-',
                        $record->visiting,
                        $record->license_plate ?? '-',
                        $record->card_number ?? '-',
                        $record->entry_time,
                        $record->exit_time ?? '-',
                    ],
                    'vehicle_pass' => [
                        Carbon::parse($record->date)->format('d/m/Y'),
                        $record->name,
                        $record->license_plate,
                        $record->purpose,
                        $record->destination,
                        $record->starting_km ?? '-',
                        $record->ending_km ?? '-',
                        $record->leaving_time ? Carbon::parse($record->leaving_time)->format('H:i') : '-',
                        $record->return_time ? Carbon::parse($record->return_time)->format('H:i') : '-',
                    ],
                    'keys' => [
                        Carbon::parse($record->date)->format('d/m/Y'),
                        $record->vehicleKey?->key_name ?? $record->facilityKey?->key_name ?? $record->boxKey?->key_name ?? '-',
                        $record->borrower_name,
                        $record->borrower_department,
                        $record->borrowed_at,
                        $record->returned_at ?? '-',
                    ],
                    default => [],
                };
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
};
?>

<section class="w-full">
    <div class="mb-6">
        <flux:button variant="outline" href="{{ route('dashboard.late') }}" wire:current="dashboard.late" wire:navigate>
            <flux:icon.arrow-left variant="micro" /> Back to Main Menu
        </flux:button>
    </div>
    <div class="flex items-center justify-between relative mb-2 w-full">
        <div>
            <flux:heading size="xl" level="1">Security Report</flux:heading>
            <flux:subheading size="lg" class="mb-6">Comprehensive overview of security records across all categories</flux:subheading>
        </div>
        
        <flux:button wire:click="export" icon="arrow-down-tray">
            Export Report
        </flux:button>
    </div>

    <flux:separator variant="subtle" class="my-4" />

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div>
            <flux:select wire:model.live="category" label="Category" placeholder="All Categories">
                <flux:select.option value="">All Categories</flux:select.option>
                <flux:select.option value="late">Late</flux:select.option>
                <flux:select.option value="break">Break</flux:select.option>
                <flux:select.option value="night_shift">Night Shift</flux:select.option>
                <flux:select.option value="visitor">Visitor</flux:select.option>
                <flux:select.option value="delivery">Delivery</flux:select.option>
                <flux:select.option value="vehicle_pass">Vehicle Pass</flux:select.option>
                <flux:select.option value="keys">Keys</flux:select.option>
            </flux:select>
        </div>

        <div>
            <flux:input wire:model.live="month" type="month" label="Monthly View" />
        </div>

        <div>
            <flux:input wire:model.live="dateFrom" type="date" label="From Date" />
        </div>

        <div>
            <flux:input wire:model.live="dateTo" type="date" label="To Date" />
        </div>

        <div>
            @if(in_array($category, ['late', 'break', 'night_shift', 'keys']))
                <flux:select wire:model.live="department" label="Department" placeholder="All Departments">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($this->departments as $dept)
                        <flux:select.option value="{{ $dept->name }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>
    </div>

    <!-- Category Count Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        @php
            $categoryLabels = [
                'late' => 'Late',
                'break' => 'Break',
                'night_shift' => 'Night Shift',
                'visitor' => 'Visitor',
                'delivery' => 'Delivery',
                'vehicle_pass' => 'Vehicle Pass',
                'keys' => 'Keys',
            ];
        @endphp
        
        @foreach($this->categoryCounts as $key => $count)
            <div 
                class="border border-accent/20 rounded-xl p-4 text-center cursor-pointer transition-all hover:shadow-md 
                    {{ $category === $key ? 'bg-accent/10 border-accent' : 'bg-white' }}"
                wire:click="$set('category', '{{ $key }}')"
            >
                <flux:text class="text-sm font-medium text-zinc-500">{{ $categoryLabels[$key] }}</flux:text>
                <div class="text-3xl font-bold text-zinc-800 mt-1">{{ $count }}</div>
            </div>
        @endforeach
    </div>

    <div class="border border-accent p-6 rounded-2xl">
        <flux:heading>Records Details</flux:heading>
        <flux:text class="mt-2 mb-4">Detailed information based on selected filters.</flux:text>

        @if($this->category && count($this->columns()) > 0)
        <flux:table>
            <flux:table.columns>
                @foreach($this->columns() as $col)
                    <flux:table.column>{{ $col }}</flux:table.column>
                @endforeach
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->records() as $record)
                    <flux:table.row>
                        @switch($this->category)
                            @case('late')
                                <flux:table.cell>{{ $record->formatted_date }}</flux:table.cell>
                                <flux:table.cell>{{ $record->name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->department }}</flux:table.cell>
                                <flux:table.cell>{{ $record->formatted_time }}</flux:table.cell>
                                <flux:table.cell>{{ $record->minutes_late }} min</flux:table.cell>
                                @break

                            @case('break')
                                <flux:table.cell>{{ $record->formatted_date }}</flux:table.cell>
                                <flux:table.cell>{{ $record->name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->department }}</flux:table.cell>
                                <flux:table.cell>{{ $record->actual_return }}</flux:table.cell>
                                <flux:table.cell>{{ $record->minutes_late }} min</flux:table.cell>
                                @break

                            @case('night_shift')
                                <flux:table.cell>{{ $record->formatted_date }}</flux:table.cell>
                                <flux:table.cell>{{ $record->name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->department }}</flux:table.cell>
                                <flux:table.cell>{{ $record->check_in_time }}</flux:table.cell>
                                <flux:table.cell>{{ $record->check_out_time ?? '-' }}</flux:table.cell>
                                @break

                            @case('visitor')
                                <flux:table.cell>{{ $record->formatted_date }}</flux:table.cell>
                                <flux:table.cell>{{ $record->name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->company ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->visiting }}</flux:table.cell>
                                <flux:table.cell>{{ $record->license_plate ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->card_number ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->entry_time }}</flux:table.cell>
                                <flux:table.cell>{{ $record->exit_time ?? '-' }}</flux:table.cell>
                                @break

                            @case('delivery')
                                <flux:table.cell>{{ $record->formatted_date }}</flux:table.cell>
                                <flux:table.cell>{{ $record->name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->company ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->visiting }}</flux:table.cell>
                                <flux:table.cell>{{ $record->license_plate ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->card_number ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->entry_time }}</flux:table.cell>
                                <flux:table.cell>{{ $record->exit_time ?? '-' }}</flux:table.cell>
                                @break

                            @case('vehicle_pass')
                                <flux:table.cell>{{ Carbon::parse($record->date)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $record->name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->license_plate }}</flux:table.cell>
                                <flux:table.cell>{{ $record->purpose }}</flux:table.cell>
                                <flux:table.cell>{{ $record->destination }}</flux:table.cell>
                                <flux:table.cell>{{ $record->starting_km ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->ending_km ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->leaving_time ? Carbon::parse($record->leaving_time)->format('H:i') : '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->return_time ? Carbon::parse($record->return_time)->format('H:i') : '-' }}</flux:table.cell>
                                @break

                            @case('keys')
                                <flux:table.cell>{{ Carbon::parse($record->date)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $record->vehicleKey?->key_name ?? $record->facilityKey?->key_name ?? $record->boxKey?->key_name ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $record->borrower_name }}</flux:table.cell>
                                <flux:table.cell>{{ $record->borrower_department }}</flux:table.cell>
                                <flux:table.cell>{{ $record->borrowed_at }}</flux:table.cell>
                                <flux:table.cell>{{ $record->returned_at ?? '-' }}</flux:table.cell>
                                @break
                        @endswitch
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ count($this->columns()) }}" class="text-center py-6">
                            <flux:text class="text-zinc-400 italic">
                                No records found for this category.
                            </flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @else
            <div class="text-center py-12">
                <flux:text class="text-zinc-400 italic">
                    Select a category to view records.
                </flux:text>
            </div>
        @endif
    </div>
</section>