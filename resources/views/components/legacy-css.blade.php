{{-- A pre-Laravel page stylesheet from resources/css/legacy, built by Vite. --}}
@props(['name'])
<link rel="stylesheet" href="{{ Vite::asset('resources/css/legacy/'.$name.'.css') }}" />
