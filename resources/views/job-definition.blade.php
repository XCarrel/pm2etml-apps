<x-app-layout>

    <div class="sm:mx-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-1 sm:gap-2 md:gap-3 bg-base-200 rounded-box sm:p-3 p-1">

        @forelse ($definitions as  $definition)
            <x-job-definition-card :job="$definition" :view-only="Auth::user()->cannot('jobs-apply')" />
        @empty
            <p>{{ __('No jobs') }}</p>
        @endforelse

    </div>

</x-app-layout>
