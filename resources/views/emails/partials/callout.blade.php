{{-- Note d’action discrète (bordure gauche) --}}
@isset($data['action_required'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 8px;">
    <tr>
        <td style="padding:12px 14px 12px 16px;border-left:3px solid {{ $calloutAccent ?? '#B3261E' }};background-color:#FAFAFA;font-size:14px;line-height:1.55;color:#3F3F46;">
            <strong style="color:#18181B;">Action requise —</strong>
            {{ $data['action_required'] }}
        </td>
    </tr>
</table>
@endisset
