<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Opération #{{ $operation->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .box { margin-top: 12px; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Opération Produits divers #{{ $operation->id }}</h1>
    <p class="muted">
        {{ $operation->produit?->code }} — {{ $operation->produit?->libelle }}
        · Statut {{ $operation->statut }}
    </p>

    <div class="box">
        <strong>Client</strong> {{ $operation->compte_client }}
        @if($operation->nom_client) ({{ $operation->nom_client }}) @endif<br>
        <strong>Date valeur</strong> {{ optional($operation->date_valeur)->format('d/m/Y') ?: '—' }}
        · <strong>Réf.</strong> {{ $operation->reference ?: '—' }}<br>
        <strong>Saisi par</strong> {{ $operation->user?->name ?: '—' }}
        @if($operation->validated_at)
            · <strong>Validé par</strong> {{ $operation->validatedBy?->name ?: '—' }}
            le {{ $operation->validated_at->format('d/m/Y H:i') }}
        @endif
    </div>

    <div class="box">
        <strong>Frais HT</strong> {{ number_format((float) $operation->frais_ht, 0, ',', ' ') }} {{ $operation->devise }}
        · <strong>TAF</strong> {{ number_format((float) $operation->montant_taf, 0, ',', ' ') }}
        · <strong>Total client</strong> {{ number_format((float) $operation->total_client, 0, ',', ' ') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Sens</th>
                <th>Compte</th>
                <th>Libellé</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach($operation->lignes as $l)
                <tr>
                    <td>{{ $l->sens }}</td>
                    <td>{{ $l->compte }}</td>
                    <td>{{ $l->libelle_ecriture }}</td>
                    <td class="right">{{ number_format((float) $l->montant, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
