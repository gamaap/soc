@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Ewindo Security System" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:link :href="route('dashboard.request')" variant="subtle">
        <div class="flex items-center space-x-4">
            <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                <flux:icon.building-office-2 color="blue" />
            </div>

            <div class="flex flex-col">
                <flux:heading size="lg">EWINDO Security System</flux:heading>
                <flux:text variant="subtle" size="sm">Real-Time Monitoring, Real-Time Protection</flux:text>
            </div>
        </div>
    </flux:link>
@endif
