<x-app-layout>

    <div class="sm:mx-6 bg-base-200 rounded-box sm:p-3 p-1" x-data="{jobNameToDelete:''}">

        @can('jobDefinitions.create')
        <div class="pb-2">
            <a class="btn btn-sm" href="{{route('jobDefinitions.create')}}"><i class="fa-solid fa-plus fa-lg"></i></a>
        </div>
        @endcan

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-1 sm:gap-2 md:gap-3">
            @forelse ($definitions as  $definition)
                <x-job-definition-card :job="$definition" :view-only="Auth::user()->cannot('jobs-apply') || Auth::user()->isAdmin()"/>
            @empty
                <p>{{ __('No jobs') }}</p>
            @endforelse
        </div>

        <x-job-definition-card-delete-modal />

    </div>

</x-app-layout>
