@extends('emails.layouts.app', [
    'accent' => '#B3261E',
    'kindLabel' => 'Notification',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;letter-spacing:-0.02em;color:#18181B;line-height:1.3;">
        {{ $data['title'] ?? 'Information' }}
    </h1>
    <p style="margin:0 0 8px;font-size:15px;line-height:1.65;color:#3F3F46;">
        {{ $data['content'] }}
    </p>

    @include('emails.partials.callout', ['calloutAccent' => '#B3261E'])
    @include('emails.partials.details')
    @include('emails.partials.button', ['buttonColor' => '#B3261E'])
@endsection
