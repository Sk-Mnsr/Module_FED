@extends('emails.layouts.app', [
    'accent' => '#B3261E',
    'kindLabel' => 'En attente',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;letter-spacing:-0.02em;color:#18181B;line-height:1.3;">
        {{ $data['title'] ?? 'Validation en attente' }}
    </h1>
    <p style="margin:0 0 8px;font-size:15px;line-height:1.65;color:#3F3F46;">
        {{ $data['content'] ?? '' }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;border:1px solid #E4E4E7;">
        <tr>
            <td style="padding:14px 16px;background-color:#FAFAFA;border-bottom:1px solid #E4E4E7;font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#71717A;font-weight:600;">
                Détails
            </td>
        </tr>
        @isset($data['reference'])
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #F4F4F5;font-size:14px;line-height:1.5;color:#18181B;">
                    <span style="display:block;font-size:12px;color:#71717A;margin-bottom:2px;">Référence</span>
                    <span style="font-weight:500;">{{ $data['reference'] }}</span>
                </td>
            </tr>
        @endisset
        @isset($data['title'])
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #F4F4F5;font-size:14px;line-height:1.5;color:#18181B;">
                    <span style="display:block;font-size:12px;color:#71717A;margin-bottom:2px;">Titre</span>
                    <span style="font-weight:500;">{{ $data['title'] }}</span>
                </td>
            </tr>
        @endisset
        @isset($data['created_at_fr'])
            <tr>
                <td style="padding:12px 16px;font-size:14px;line-height:1.5;color:#18181B;">
                    <span style="display:block;font-size:12px;color:#71717A;margin-bottom:2px;">Créé le</span>
                    <span style="font-weight:500;">{{ $data['created_at_fr'] }}</span>
                </td>
            </tr>
        @endisset
    </table>

    @include('emails.partials.button', ['buttonColor' => '#B3261E'])
@endsection
