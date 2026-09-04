@extends('payment-support.layout')

@section('title', 'Support link unavailable')

@section('content')

    <div class="card center">
        <div class="status-icon bad">!</div>
        <h1>We could not open this link</h1>
        <p class="muted small">{{ $message }}</p>
        <p class="muted small">
            If somebody sent you this link, ask them to generate a new one from their eMed app.
        </p>
    </div>

@endsection
