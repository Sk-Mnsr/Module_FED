@extends('emails.layouts.app', [
    'accent' => '#B3261E',
    'kindLabel' => 'Rejet',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;letter-spacing:-0.02em;color:#18181B;line-height:1.3;">
        {{ $data['title'] ?? 'Demande rejetée' }}
    </h1>
    <p style="margin:0 0 8px;font-size:15px;line-height:1.65;color:#3F3F46;">
        {{ $data['content'] }}
    </p>

    @isset($data['rejection_reason'])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 8px;">
            <tr>
                <td style="padding:12px 14px 12px 16px;border-left:3px solid #B3261E;background-color:#FAFAFA;font-size:14px;line-height:1.55;color:#3F3F46;">
                    <strong style="color:#18181B;">Motif —</strong>
                    {{ $data['rejection_reason'] }}
                </td>
            </tr>
        </table>
    @endisset

    @include('emails.partials.details', ['detailsTitle' => 'Détails de la demande'])

    @isset($data['actions_available'])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 8px;">
            <tr>
                <td style="padding:0;font-size:14px;line-height:1.6;color:#3F3F46;">
                    <p style="margin:0 0 10px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#71717A;font-weight:600;">
                        Actions possibles
                    </p>
                    @foreach($data['actions_available'] as $action)
                        <p style="margin:0 0 8px;">
                            @isset($action['url'])
                                <a href="{{ $action['url'] }}" style="color:#B3261E;text-decoration:none;font-weight:600;">
                                    {{ $action['text'] }}
                                </a>
                            @else
                                {{ $action['text'] }}
                            @endisset
                        </p>
                    @endforeach
                </td>
            </tr>
        </table>
    @endisset

    @isset($data['modify_url'])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px;">
            <tr>
                <td align="center">
                    <a href="{{ $data['modify_url'] }}"
                       style="display:inline-block;background-color:#B3261E;color:#FFFFFF;text-decoration:none;font-size:14px;font-weight:600;letter-spacing:0.02em;padding:12px 28px;border-radius:2px;">
                        {{ $data['modify_text'] ?? 'Modifier la demande' }}
                    </a>
                </td>
            </tr>
        </table>
    @endisset
@endsection

@section('footer_note')
    Pour toute question, contactez l’équipe support.
@endsection
