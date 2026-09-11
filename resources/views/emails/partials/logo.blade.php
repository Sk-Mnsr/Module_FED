@php
    $logoPath = collect([
        public_path('logo_Cofina.png'),
        public_path('logo.png'),
    ])->first(fn (string $path) => is_file($path));
@endphp
@if ($logoPath)
    <img src="{{ $message->embed($logoPath) }}" alt="Cofina" width="160" style="display:inline-block;max-width:160px;height:auto;border:0;">
@else
    <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:600;color:#B3261E;letter-spacing:-0.02em;">cofina</span>
@endif
