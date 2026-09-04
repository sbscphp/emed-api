@extends('payment-support.layout')

@php
    $currency = $support['currency'] === 'NGN' ? '₦' : $support['currency'] . ' ';
    $name = $support['patient_first_name'];
@endphp

@section('title', $name . ' is asking for your support')

@section('content')

    @if (session('error'))
        <div class="alert error">{{ session('error') }}</div>
    @endif

    <div class="card center">
        <div class="avatar">{{ strtoupper(substr($name, 0, 1)) }}</div>
        <h1>{{ $name }} is asking for your support 💜</h1>
        <p class="muted small">They need help towards their healthcare expenses at {{ $support['hospital']['name'] }}.</p>
    </div>

    <div class="card">
        <div class="goal">
            <div class="goal-label">Healthcare support needed</div>
            <div class="goal-value">{{ $currency }}{{ number_format($support['target_amount'], 2) }}</div>
            <div class="small muted">For {{ $support['service'] }}</div>
        </div>

        <div class="amount-row">
            <div>
                <div class="amount-label">Amount raised</div>
                <div class="amount-value raised">{{ $currency }}{{ number_format($support['raised_amount'], 2) }}</div>
            </div>
            <div style="text-align:right">
                <div class="amount-label">Amount left to raise</div>
                <div class="amount-value">{{ $currency }}{{ number_format($support['outstanding_amount'], 2) }}</div>
            </div>
        </div>

        <div class="bar"><span style="width: {{ $support['progress_percent'] }}%"></span></div>
        <div class="bar-meta">
            <span>{{ $support['supporters_count'] }} {{ Str::plural('supporter', $support['supporters_count']) }}</span>
            <span>{{ $support['progress_percent'] }}%</span>
        </div>

        @if (!empty($support['message']))
            <p class="small muted" style="margin-top:14px">“{{ $support['message'] }}”</p>
        @endif
    </div>

    @if (!$support['is_open'])

        {{-- Nothing more can be given: the bill is covered, or the link has been
             closed or has run past its date. --}}
        <div class="card center">
            <div class="status-icon ok">✓</div>
            <h2>
                @if ($support['status'] === 'Completed')
                    This bill has been fully covered
                @else
                    This support link is no longer active
                @endif
            </h2>
            <p class="muted small">
                @if ($support['status'] === 'Completed')
                    Thank you to everyone who gave.
                @else
                    Ask {{ $name }} for a new link if you would still like to help.
                @endif
            </p>
        </div>

    @else

        <form class="card" method="POST" action="{{ route('payment-support.contribute', $token) }}">
            @csrf

            <h2>Choose an amount to support</h2>
            <p class="small muted" style="margin-top:-8px">Give the amount you are comfortable with.</p>

            <label for="amount">Amount ({{ $support['currency'] }})</label>
            <input type="number" id="amount" name="amount" step="0.01" min="1"
                   max="{{ $support['outstanding_amount'] }}"
                   value="{{ old('amount') }}" placeholder="0.00" required>

            <div class="chips">
                @foreach ($support['suggested_amounts'] as $suggested)
                    <button type="button" class="chip"
                            onclick="document.getElementById('amount').value = '{{ $suggested }}'">
                        {{ $currency }}{{ number_format($suggested, 0) }}
                    </button>
                @endforeach
            </div>

            <label for="name">Your name</label>
            <input type="text" id="name" name="name" maxlength="120"
                   value="{{ old('name') }}" placeholder="So {{ $name }} can thank you">

            <label for="email">Your email</label>
            <input type="email" id="email" name="email" maxlength="255"
                   value="{{ old('email') }}" placeholder="For your receipt" required>

            <div class="check">
                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                       {{ old('is_anonymous') ? 'checked' : '' }}>
                <label for="is_anonymous" style="margin:0; font-weight:500">
                    Give anonymously — your name will not be shown on the list of supporters.
                </label>
            </div>

            <div class="note">
                <span>🔒</span>
                <div>
                    <strong>Every contribution makes a difference.</strong><br>
                    Your support is secure and goes directly to {{ $support['hospital']['name'] }} towards
                    {{ $name }}'s bill. You will never see their medical records.
                </div>
            </div>

            <button type="submit" class="primary">Support {{ $name }} →</button>
        </form>

        <p class="foot">You do not need an eMed account to support.</p>

    @endif

    @if (!empty($support['supporters']))
        <div class="card">
            <h2>Recent support</h2>
            <div class="supporters">
                @foreach ($support['supporters'] as $supporter)
                    <div class="supporter">
                        <div>
                            {{ $supporter['name'] }}
                            <div class="when">{{ $supporter['paid_at'] ? \Carbon\Carbon::parse($supporter['paid_at'])->diffForHumans() : '' }}</div>
                        </div>
                        <div><strong>{{ $currency }}{{ number_format($supporter['amount'], 2) }}</strong></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

@endsection
