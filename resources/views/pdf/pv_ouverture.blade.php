<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PV d'Ouverture des Plis - {{ $appelOffre->reference }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .subtitle { font-size: 14px; color: #666; }
        .content { margin-bottom: 30px; }
        .info-row { margin-bottom: 10px; }
        .label { font-weight: bold; width: 150px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .signatures { margin-top: 50px; width: 100%; }
        .signature-box { float: left; width: 33%; text-align: center; }
        .signature-line { margin-top: 50px; border-top: 1px dashed #999; width: 80%; display: inline-block; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">Procès-Verbal d'Ouverture des Plis</div>
        <div class="subtitle">Appel d'Offres N° {{ $appelOffre->reference }}</div>
    </div>

    <div class="content">
        <div class="info-row"><span class="label">Objet :</span> {{ $appelOffre->objet }}</div>
        <div class="info-row"><span class="label">Date limite :</span> {{ \Carbon\Carbon::parse($appelOffre->date_limite_soumission)->format('d/m/Y H:i') }}</div>
        <div class="info-row"><span class="label">Date d'ouverture :</span> {{ date('d/m/Y H:i') }}</div>
        <div class="info-row"><span class="label">Comité d'évaluation :</span> {{ $appelOffre->comite ? $appelOffre->comite->nom : 'Non défini' }}</div>
    </div>

    <div class="content">
        <h3 style="margin-bottom: 10px;">Liste des offres reçues ({{ $appelOffre->offres->count() }})</h3>
        
        @if($appelOffre->offres->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fournisseur / Soumissionnaire</th>
                    <th>Date et Heure de dépôt</th>
                    <th>Observations à l'ouverture</th>
                </tr>
            </thead>
            <tbody>
                @foreach($appelOffre->offres as $index => $offre)
                <tr>
                    <td style="text-align: center; width: 30px;">{{ $index + 1 }}</td>
                    <td>{{ $offre->nom_fournisseur ?: 'Fournisseur ID: ' . $offre->fournisseur_id }}</td>
                    <td>{{ \Carbon\Carbon::parse($offre->created_at)->format('d/m/Y H:i:s') }}</td>
                    <td>Pli scellé intact</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>Aucune offre n'a été soumise pour cet appel d'offres avant la date limite.</p>
        @endif
    </div>

    <div class="content" style="margin-top: 40px;">
        <p>L'ouverture des plis s'est déroulée de manière transparente via la plateforme numérique. Le présent PV atteste de la réception et de l'intégrité des offres listées ci-dessus.</p>
    </div>

    <div class="signatures">
        @if($appelOffre->comite && $appelOffre->comite->membres)
            @foreach($appelOffre->comite->membres->take(3) as $membre)
            <div class="signature-box">
                <p><strong>{{ ucfirst($membre->pivot->role) }}</strong><br>{{ $membre->name }}</p>
                <div class="signature-line"></div>
            </div>
            @endforeach
        @else
            <div class="signature-box">
                <p><strong>Le Responsable</strong><br>_______________________</p>
            </div>
        @endif
        <div style="clear: both;"></div>
    </div>

</body>
</html>
