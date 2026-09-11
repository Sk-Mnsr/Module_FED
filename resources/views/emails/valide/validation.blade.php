@extends('emails.layouts.app', [
    'accent' => '#166534',
    'kindLabel' => 'Validation',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;letter-spacing:-0.02em;color:#18181B;line-height:1.3;">
        {{ $data['title'] ?? 'Validation' }}
    </h1>
    <p style="margin:0 0 8px;font-size:15px;line-height:1.65;color:#3F3F46;">
        {{ $data['content'] }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 8px;">
        <tr>
            <td style="padding:12px 14px 12px 16px;border-left:3px solid #166534;background-color:#FAFAFA;font-size:14px;line-height:1.55;color:#3F3F46;">
                {{ $data['success_message'] ?? 'Opération validée avec succès.' }}
            </td>
        </tr>
    </table>

    @include('emails.partials.details', ['detailsTitle' => 'Détails de la validation'])

    @isset($data['validation_info'])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 8px;">
            <tr>
                <td style="padding:0;font-size:14px;line-height:1.6;color:#3F3F46;">
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717A;font-weight:600;">
                        Informations
                    </p>
                    @foreach($data['validation_info'] as $info)
                        <p style="margin:0 0 6px;">{{ $info }}</p>
                    @endforeach
                </td>
            </tr>
        </table>
    @endisset

    @include('emails.partials.button', ['buttonColor' => '#166534'])
@endsection
