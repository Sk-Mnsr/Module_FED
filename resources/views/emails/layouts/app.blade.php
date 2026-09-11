{{-- Layout e-mail Cofina — léger, professionnel (HTML table pour clients mail). --}}
@php
    $accent = $accent ?? '#B3261E';
    $kindLabel = $kindLabel ?? 'Notification';
    $appName = config('app.name', 'COFI-COMPTA');
    $year = now()->year;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>{{ $data['subject'] ?? $kindLabel }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F4F5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F4F5;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#FFFFFF;border:1px solid #E4E4E7;">
                    <tr>
                        <td style="height:3px;line-height:3px;font-size:0;background-color:{{ $accent }};">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:28px 36px 8px;text-align:center;">
                            @include('emails.partials.logo')
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 36px 20px;text-align:center;">
                            <p style="margin:0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#71717A;">
                                {{ $kindLabel }} · {{ $appName }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 36px 8px;color:#18181B;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 36px 32px;border-top:1px solid #E4E4E7;">
                            <p style="margin:0 0 8px;text-align:center;font-size:12px;color:#A1A1AA;">
                                © {{ $year }} Cofina Sénégal. Tous droits réservés.
                            </p>
                            @hasSection('footer_note')
                                <p style="margin:0;text-align:center;font-size:12px;color:#71717A;line-height:1.5;">
                                    @yield('footer_note')
                                </p>
                            @elseif (! empty($data['footer_note'] ?? null))
                                <p style="margin:0;text-align:center;font-size:12px;color:#71717A;line-height:1.5;">
                                    {{ $data['footer_note'] }}
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
