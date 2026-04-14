<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<section class="w-full">
    @include('partials.dashboard-heading')

    <x-pages::dashboard.layout>
        <div class="border border-accent p-6 rounded-2xl my-6">
            <flux:heading>Key Management System</flux:heading>
            <flux:text class="mt-2">Track and manage vehicle and facility keys with complete borrow / return history.</flux:text>

            <flux:navbar class="mt-2 flex items-center justify-evenly gap-3">
                <flux:navbar.item :href="route('dashboard.keys.vehicle')" wire:current="dashboard.keys.vehicle" class="w-full text-center" wire:navigate>Vehicle Keys</flux:navbar.item>
                <flux:navbar.item :href="route('dashboard.keys.box')" wire:current="dashboard.keys.box" class="w-full text-center" wire:navigate>Box Keys</flux:navbar.item>
                <flux:navbar.item :href="route('dashboard.keys.facility')" wire:current="dashboard.keys.facility" class="w-full text-center" wire:navigate>Facility Keys</flux:navbar.item>
            </flux:navbar>

            <div class="mt-6">
                {{ $slot }}
            </div>
        </div>
    </x-pages::dashboard.layout>
</section>