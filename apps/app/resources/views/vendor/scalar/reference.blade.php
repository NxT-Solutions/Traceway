@extends('scalar::layout')

{{-- Scalar's own view, with the page's nonce so its start script may run. See SendSecurityHeaders. --}}
@section('content')
    <div id="app"></div>

    <script src="{{ \Scalar\Facades\Scalar::cdn() }}" nonce="{{ Vite::cspNonce() }}"></script>

    <script nonce="{{ Vite::cspNonce() }}">
        Scalar.createApiReference('#app', {!! \Scalar\Facades\Scalar::configurationJson() !!})
    </script>
@endsection
