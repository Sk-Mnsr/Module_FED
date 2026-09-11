{{-- Bouton CTA --}}
@isset($data['action_url'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px;">
    <tr>
        <td align="center">
            <a href="{{ $data['action_url'] }}"
               style="display:inline-block;background-color:{{ $buttonColor ?? '#B3261E' }};color:#FFFFFF;text-decoration:none;font-size:14px;font-weight:600;letter-spacing:0.02em;padding:12px 28px;border-radius:2px;">
                {{ $buttonLabel ?? ($data['action_text'] ?? 'Voir les détails') }}
            </a>
        </td>
    </tr>
</table>
@endisset
