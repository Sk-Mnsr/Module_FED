@php
    $logoPath = collect([
        public_path('logo_Cofina.png'),
        public_path('logo.png'),
    ])->first(fn (string $path) => is_file($path));
@endphp
@if ($logoPath)
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="{{ $message->embed($logoPath) }}" alt="Logo" style="max-width: 220px;">
    </div>
@endif
