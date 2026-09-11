{{-- Bloc détails clé / valeur --}}
@isset($data['details'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;border:1px solid #E4E4E7;">
    <tr>
        <td style="padding:14px 16px;background-color:#FAFAFA;border-bottom:1px solid #E4E4E7;font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#71717A;font-weight:600;">
            {{ $detailsTitle ?? 'Détails' }}
        </td>
    </tr>
    @foreach($data['details'] as $label => $value)
        <tr>
            <td style="padding:12px 16px;border-bottom:1px solid #F4F4F5;font-size:14px;line-height:1.5;color:#18181B;">
                <span style="display:block;font-size:12px;color:#71717A;margin-bottom:2px;">{{ $label }}</span>
                <span style="font-weight:500;word-break:break-word;">{{ $value }}</span>
            </td>
        </tr>
    @endforeach
</table>
@endisset
