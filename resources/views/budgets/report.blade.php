<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Budget {{ $departmentName }} {{ $year }}</title>
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
            .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
            .title { font-size: 18px; font-weight: 700; }
            .meta { font-size: 12px; color: #374151; }
            table { width: 100%; border-collapse: collapse; }
            thead th { background: #111827; color: #ffffff; text-transform: uppercase; font-size: 10px; padding: 8px 6px; text-align: left; }
            tbody td { padding: 6px; border-bottom: 1px solid #e5e7eb; background: #ecfdf5; }
            tfoot td { padding: 8px 6px; background: #111827; color: #ffffff; font-weight: 700; }
            .text-right { text-align: right; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">Budget annuel</div>
            <div class="meta">
                <div>Département: {{ $departmentName }}</div>
                <div>Année: {{ $year }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Code ligne</th>
                    <th>Libellé de la dépense</th>
                    <th>Responsable</th>
                    <th>Famille</th>
                    <th>Catégorie</th>
                    <th>Montant estimé</th>
                    <th>Montant consommé</th>
                    <th>Montant stock</th>
                    <th>Compte GL</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($budget->lines as $index => $line)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $line->code ?? '-' }}</td>
                        <td>{{ $line->label }}</td>
                        <td>{{ $line->responsable ?? '-' }}</td>
                        <td>{{ $line->article?->sous_categorie?->categorie?->famille?->nom ?? '-' }}</td>
                        <td>{{ $line->article?->sous_categorie?->categorie?->nom ?? '-' }}</td>
                        <td class="text-right">{{ number_format((float) ($line->montant_estime ?? 0), 0, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format((float) ($line->montant_consomme ?? 0), 0, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format((float) ($line->montant_stock ?? 0), 0, ',', ' ') }}</td>
                        <td>{{ $line->compte_gl ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">Aucune ligne budgétaire.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6">TOTAL BUDGET</td>
                    <td class="text-right">{{ number_format((float) ($totalEstime ?? 0), 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format((float) ($budget->lines->sum('montant_consomme') ?? 0), 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format((float) ($budget->lines->sum('montant_stock') ?? 0), 0, ',', ' ') }}</td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </body>
</html>
