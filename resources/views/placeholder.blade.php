<x-layouts.app :title="$module">

    <x-card>
        <div class="py-8">
            <x-empty-state
                :title="__('app.under_construction')"
                :description="$description ?? __('app.under_construction_desc')"
                icon="reports"
            />
        </div>
    </x-card>

</x-layouts.app>