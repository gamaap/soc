<x-layouts::app.header :title="$title ?? null">
    <flux:main>
        <div class="my-3">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.header>