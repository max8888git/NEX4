{{-- A pre-Laravel page script from resources/js/legacy, built by Vite but
     loaded as a classic script so it still runs in document order. --}}
@props(['name', 'defer' => false])
<script src="{{ Vite::asset('resources/js/legacy/'.$name.'.js') }}"{!! $defer ? ' defer' : '' !!}></script>
