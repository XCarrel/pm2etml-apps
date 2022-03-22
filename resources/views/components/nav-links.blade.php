@props(['li'=>false])

@php
    $links = [
        'dashboard'=> __('Dashboard'),
        'jobs'=> __('Jobs')
    ];
@endphp

@foreach($links as $route=>$label)
    <x-nav-link :li="$li" :href="route($route)" :active="request()->routeIs($route)"
                class="btn btn-ghost normal-case mx-2"
                onclick="this.classList.add('loading')">
        {{ $label }}
    </x-nav-link>
@endforeach


