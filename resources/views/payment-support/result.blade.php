@extends('payment-support.layout')

@php
    $currency = $support['currency'] === 'NGN' ? '₦' : $support['currency'] . ' ';
    $name = $support['patient_first_name'];
@endphp

@section('title', $settled ? 'Thank you for your support' : 'Payment not completed')

@section('content')

    <div class="card center">
        <div class="status-icon {{ $settled ? 'ok' : 'bad' }}">{{ $settled ? '✓' : '!' }}</div>

        @if ($settled)
            <h1>Thank you 💜</h1>
            <p class="muted small">
                Your payment of <strong>{{ $currency }}{{ number_format($amount, 2) }}</strong> was successful
                and has been applied to {{ $name }}'s bill at {{ $support['hospital']['name'] }}.
                A receipt is on its way to your email.
            </p>
        @else
            <h1>That payment did not complete</h1>
            <p class="muted small">
                Nothing has been charged. You can try again whenever you are ready.
            </p>
        @endif
    </div>

    <div class="card">
        <div class="kv"><span>Reference</span><span>{{ $reference }}</span></div>
        <div class="kv"><span>Hospital</span><span>{{ $support['hospital']['name'] }}</span></div>
        <div class="kv"><span>Towards</span><span>{{ $support['service'] }}</span></div>
        <div class="kv"><span>Amount</span><span>{{ $currency }}{{ number_format($amount, 2) }}</span></div>
    </div>

    <div class="card">
        <h2>Where {{ $name }} is now</h2>

        <div class="amount-row">
            <div>
                <div class="amount-label">Raised</div>
                <div class="amount-value raised">{{ $currency }}{{ number_format($support['raised_amount'], 2) }}</div>
            </div>
            <div style="text-align:right">
                <div class="amount-label">Still needed</div>
                <div class="amount-value">{{ $currency }}{{ number_format($support['outstanding_amount'], 2) }}</div>
            </div>
        </div>

        <div class="bar"><span style="width: {{ $support['progress_percent'] }}%"></span></div>
        <div class="bar-meta">
            <span>{{ $support['supporters_count'] }} {{ Str::plural('supporter', $support['supporters_count']) }}</span>
            <span>{{ $support['progress_percent'] }}%</span>
        </div>

        @if ($support['status'] === 'Completed')
            <p class="small" style="margin-top:14px; color:var(--green)">
                <strong>This bill is now fully covered.</strong>
            </p>
        @elseif ($support['is_open'])
            <a href="{{ route('payment-support.show', $token) }}">
                <button type="button" class="primary">Give again</button>
            </a>
        @endif
    </div>

@endsection
