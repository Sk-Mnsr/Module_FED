<?php
    /** @var \App\Models\OdClasseur $classeur */
    /** @var array $parsed */
    $rows = $parsed['rows'] ?? [];
    $devise = $parsed['devise'] ?? 'XOF';

    $fmt = function ($v) {
        if ($v === null || $v === '') {
            return '';
        }
        $v = (float) $v;
        $decimals = ($v == floor($v)) ? 0 : 2;
        return number_format($v, $decimals, ',', ' ');
    };
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Pièce comptable — {{ $classeur->numero_piece }}</title>
    <style>
        @page { margin: 18px 22px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        .header-top { width: 100%; margin-bottom: 8px; }
        .header-top td { vertical-align: middle; }
        .logo { width: 110px; }
        .title { text-align: center; font-size: 15px; font-weight: bold; text-decoration: underline; letter-spacing: 0.5px; }
        .meta { width: 100%; margin: 10px 0 14px; }
        .meta td { vertical-align: top; padding: 1px 0; }
        .meta .label { color: #1a3e8c; font-weight: bold; width: 70px; }
        .meta .label-r { color: #1a3e8c; font-weight: bold; width: 70px; }
        .meta .val { color: #b3261e; font-weight: bold; }
        .meta-left { width: 55%; }
        .meta-right { width: 45%; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #222; padding: 3px 4px; text-align: left; }
        table.lines th { background: #ffffff; font-weight: bold; color: #1a3e8c; font-size: 8px; }
        table.lines td { font-size: 8px; }
        .num { text-align: right; }
        .footer { width: 100%; margin-top: 34px; }
        .footer td { font-weight: bold; }
    </style>
</head>
<body>
    <table class="header-top">
        <tr>
            <td style="width: 25%;">
                @if(file_exists(public_path('logo_Cofina.png')))
                    <img class="logo" src="{{ public_path('logo_Cofina.png') }}" alt="Cofina">
                @else
                    <strong style="font-size:16px;color:#b3261e;">cofina</strong>
                @endif
            </td>
            <td style="width: 50%;">
                <div class="title">PIECES COMPTABLE</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="meta-left">
                <table>
                    <tr><td class="label">N° BATCH :</td><td class="val">{{ $classeur->numero_batch }}</td></tr>
                    <tr><td class="label">NB Cr :</td><td class="val">{{ $parsed['nb_credit'] ?? 0 }} écriture(s)</td></tr>
                    <tr><td class="label">NB Dr :</td><td class="val">{{ $parsed['nb_debit'] ?? 0 }} écriture(s)</td></tr>
                    <tr><td class="label">Date :</td><td class="val">{{ $date }}</td></tr>
                </table>
            </td>
            <td class="meta-right">
                <table>
                    <tr><td class="label-r">USER_ID :</td><td class="val">{{ $userId }}</td></tr>
                    <tr><td class="label-r">Total Cr :</td><td class="val">{{ $fmt($parsed['total_credit'] ?? 0) }} {{ $devise }}</td></tr>
                    <tr><td class="label-r">Total Dr :</td><td class="val">{{ $fmt($parsed['total_debit'] ?? 0) }} {{ $devise }}</td></tr>
                    <tr><td class="label-r">Heure :</td><td class="val">{{ $heure }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:26px;">Id</th>
                <th style="width:40px;">Agence</th>
                <th>N° Compte</th>
                <th>Compte</th>
                <th class="num" style="width:70px;">Montant Débit</th>
                <th class="num" style="width:70px;">Montant Crédit</th>
                <th style="width:55px;">Code Opération</th>
                <th>Related Account</th>
                <th>Libellé Ecriture</th>
                <th style="width:60px;">Date Valeur</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <?php
                    $sens = $row['_sens'] ?? ($row['sens'] ?? '');
                    $montant = $row['_montant'] ?? 0;
                ?>
                <tr>
                    <td>{{ $row['numero'] ?? ($i + 1) }}</td>
                    <td>{{ $row['code_agence'] ?? '' }}</td>
                    <td>{{ $row['no_compte'] ?? '' }}</td>
                    <td></td>
                    <td class="num">{{ $sens === 'D' ? $fmt($montant) : '' }}</td>
                    <td class="num">{{ $sens === 'C' ? $fmt($montant) : '' }}</td>
                    <td>{{ $row['code_operation'] ?? '' }}</td>
                    <td>{{ $row['related_account'] ?? '' }}</td>
                    <td>{{ $row['libelle_ecriture'] ?? '' }}</td>
                    <td>{{ $row['date_de_valeur'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;">Aucune écriture exploitable dans le fichier d’intégration.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td style="width:50%;">Maker :</td>
            <td style="width:50%; text-align:right;">Checker :</td>
        </tr>
    </table>
</body>
</html>
