<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon — {{ $transfer->bon_numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .muted { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Bon d’approvisionnement Coficarte</h1>
    <p class="muted">Référence : <strong>{{ $transfer->bon_numero }}</strong></p>
    <p>Date d’émission : {{ $transfer->created_at?->format('d/m/Y H:i') }}</p>

    <table>
        <tr><th>Plage cartes (représentation)</th><td>{{ $transfer->debut_plage }} → {{ $transfer->fin_plage }} @if(is_array($transfer->card_ids) && count($transfer->card_ids) > 0)<span class="muted"> — {{ count($transfer->card_ids) }} carte(s) listée(s)</span>@endif</td></tr>
        <tr><th>Destinataire</th><td>{{ $transfer->receveur }}</td></tr>
        <tr><th>Initiateur (monétique)</th><td>{{ $transfer->user?->name ?? '—' }}</td></tr>
        @if($transfer->supplyRequest)
            <tr><th>Demande d’origine</th><td>#{{ $transfer->supplyRequest->id }} — {{ $transfer->supplyRequest->agence?->nom ?? '—' }} (demandé : {{ $transfer->supplyRequest->quantite_demandee }} — déjà livré : {{ $transfer->supplyRequest->quantite_livree ?? 0 }})</td></tr>
            @if($transfer->supply_request_completion === 'close')
                <tr><th>Suivi demande</th><td>Clôture de la demande prévue après réception de ce bon (livraison partielle acceptée si besoin).</td></tr>
            @elseif($transfer->supply_request_completion === 'continue')
                <tr><th>Suivi demande</th><td>Demande maintenue ouverte tant que la quantité totale réceptionnée n’atteint pas le montant demandé.</td></tr>
            @endif
        @endif
        <tr><th>Commentaire</th><td>{{ $transfer->commentaire ?? '—' }}</td></tr>
        <tr><th>Statut transfert</th><td>{{ $transfer->status }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 24px;">Document généré depuis le module FED — Coficarte.</p>
</body>
</html>
