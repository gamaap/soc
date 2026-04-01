<div class="my-4">
    <flux:navbar class="-mb-px flex justify-between">
        {{-- <flux:navbar.item icon="user-check" :href="route('dashboard.request')" wire:current="dashboard.request" wire:navigate>
            Request
        </flux:navbar.item> --}}
        <flux:navbar.item icon="clock" :href="route('dashboard.late')" wire:current="dashboard.late" wire:navigate>
            Late
        </flux:navbar.item>
        <flux:navbar.item icon="coffee" :href="route('dashboard.break')" wire:current="dashboard.break" wire:navigate>
            Break Time
        </flux:navbar.item>
        <flux:navbar.item icon="clock-4" :href="route('dashboard.night-shift')" wire:current="dashboard.night-shift" wire:navigate>
            Night Shift
        </flux:navbar.item>
        <flux:navbar.item icon="users" :href="route('dashboard.visitor')" wire:current="dashboard.visitor" wire:navigate>
            Visitor
        </flux:navbar.item>
        <flux:navbar.item icon="truck" :href="route('dashboard.delivery')" wire:current="dashboard.delivery" wire:navigate>
            Delivery
        </flux:navbar.item>
        <flux:navbar.item icon="car" :href="route('dashboard.vehicle-pass')" wire:current="dashboard.vehicle-pass" wire:navigate>
            Vehicle Pass
        </flux:navbar.item>
        <flux:navbar.item icon="key" :href="route('dashboard.keys.vehicle')" :current="request()->routeIs('dashboard.keys.*')" wire:navigate>
            Keys
        </flux:navbar.item>
    </flux:navbar>
</div>

<div class="mt-5 w-full">
    {{ $slot }}
</div>