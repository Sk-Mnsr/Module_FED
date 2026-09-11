@extends('emails.layouts.app', [
    'accent' => '#B3261E',
    'kindLabel' => 'Alerte',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;letter-spacing:-0.02em;color:#18181B;line-height:1.3;">
        {{ $data['title'] ?? 'Alerte' }}
    </h1>
    <p style="margin:0 0 8px;font-size:15px;line-height:1.65;color:#3F3F46;">
        {{ $data['content'] }}
    </p>

    @isset($data['alert_level'])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 8px;">
            <tr>
                <td style="padding:12px 14px 12px 16px;border-left:3px solid #B3261E;background-color:#FAFAFA;font-size:14px;line-height:1.55;color:#3F3F46;">
                    <strong style="color:#18181B;">Niveau —</strong>
                    {{ $data['alert_level'] }}
                </td>
            </tr>
        </table>
    @endisset

    @include('emails.partials.callout', ['calloutAccent' => '#B3261E'])
    @include('emails.partials.details', ['detailsTitle' => 'Détails de l’alerte'])
    @include('emails.partials.button', [
        'buttonColor' => '#B3261E',
        'buttonLabel' => $data['action_text'] ?? 'Traiter l’alerte',
    ])
@endsection

@section('footer_note')
    Cet e-mail contient une alerte importante. Merci d’en prendre connaissance.
@endsection
