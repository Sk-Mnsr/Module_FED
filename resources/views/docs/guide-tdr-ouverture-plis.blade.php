<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Guide Laravel — TDR à l'ouverture des plis</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0 0 6px; color: #0f172a; }
        h2 { font-size: 14px; margin: 18px 0 8px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 12px 0 6px; color: #1e293b; }
        p, li { margin: 0 0 6px; }
        .muted { color: #64748b; font-size: 10px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; margin: 8px 0 12px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        code, pre { font-family: DejaVu Sans Mono, monospace; font-size: 9px; }
        pre { background: #0f172a; color: #e2e8f0; padding: 10px; white-space: pre-wrap; word-wrap: break-word; margin: 8px 0 12px; }
        ul { margin: 0 0 10px; padding-left: 18px; }
        .step { margin-bottom: 4px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>Guide d'implémentation Laravel</h1>
    <p><strong>De la création du TDR jusqu'à l'ouverture des plis</strong></p>
    <p class="muted">Document technique réutilisable — flux d'appels d'offres / comité / clé partagée / session d'ouverture.</p>

    <div class="box">
        <strong>Objectif :</strong> permettre à un projet Laravel d'implémenter le parcours :
        création TDR → publication → comité + fragments de clé → soumission des offres →
        session d'ouverture → vérification de la clé → déverrouillage + PV.
    </div>

    <h2>1. Parcours métier</h2>
    <ol>
        <li class="step"><strong>Créer le TDR</strong> — référence auto <code>TDR-AAAA-NNNN</code>, objet, dates, critères, fichiers DAO/CDC, statut <code>brouillon</code>.</li>
        <li class="step"><strong>Publier</strong> — statut <code>publie</code>, invitations fournisseurs.</li>
        <li class="step"><strong>Créer le comité</strong> — membres + rôles ; génération d'une clé maître ; découpage en fragments ; hash stocké ; notifications.</li>
        <li class="step"><strong>Soumettre les offres</strong> — avant la date limite ; plis scellés = affichage anonyme.</li>
        <li class="step"><strong>Ouvrir les plis</strong> — après date limite ; saisie de la clé reconstituée ; si OK → <code>is_plis_ouverts = true</code>, statut <code>en_evaluation</code> ; PV PDF.</li>
    </ol>

    <h2>2. Modèle de données</h2>
    <table>
        <thead>
            <tr><th>Table</th><th>Champs clés</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><code>appel_offres</code></td>
                <td>reference, objet, description, dates, type_publication, statut, cle_ouverture_hash, is_plis_ouverts, creator_id</td>
            </tr>
            <tr>
                <td><code>criteres_appel_offre</code></td>
                <td>appel_offre_id, nom, ponderation, type, note_maximale</td>
            </tr>
            <tr>
                <td><code>comites</code></td>
                <td>appel_offre_id, nom</td>
            </tr>
            <tr>
                <td><code>comite_user</code></td>
                <td>comite_id, user_id, role (president|membre|secretaire)</td>
            </tr>
            <tr>
                <td><code>offres</code></td>
                <td>appel_offre_id, nom_fournisseur, pièces / montant selon besoin</td>
            </tr>
        </tbody>
    </table>
    <p>Statuts utiles : <code>brouillon</code> → <code>publie</code> → <code>en_evaluation</code>.</p>

    <h2>3. Migration (extrait)</h2>
<pre>Schema::create('appel_offres', function (Blueprint $table) {
    $table->id();
    $table->string('reference')->unique();
    $table->string('objet');
    $table->text('description');
    $table->dateTime('date_lancement')->nullable();
    $table->dateTime('date_limite_soumission');
    $table->enum('type_publication', ['interne', 'externe']);
    $table->string('statut')->default('brouillon');
    $table->string('cle_ouverture_hash')->nullable();
    $table->boolean('is_plis_ouverts')->default(false);
    $table->foreignId('creator_id')->constrained('users');
    $table->timestamps();
});</pre>

    <h2>4. Routes Laravel</h2>
<pre>Route::middleware(['auth'])->group(function () {
    Route::resource('appel-offres', AppelOffreController::class);
    Route::post('appel-offres/{appelOffre}/publish', [AppelOffreController::class, 'publish']);
    Route::post('appel-offres/{appelOffre}/comites', [ComiteController::class, 'store']);
    Route::post('appel-offres/{appelOffre}/offres', [OffreController::class, 'store']);
    Route::get('appel-offres/{appelOffre}/opening-session', [AppelOffreController::class, 'openingSession']);
    Route::post('appel-offres/{appelOffre}/start-evaluation', [AppelOffreController::class, 'startEvaluation']);
    Route::get('appel-offres/{appelOffre}/pv-ouverture', [AppelOffreController::class, 'pvOuverture']);
});</pre>

    <div class="page-break"></div>

    <h2>5. Création + publication du TDR</h2>
<pre>// store()
$reference = 'TDR-'.now()->format('Y').'-'.str_pad((string) ((AppelOffre::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);

$ao = AppelOffre::create([
    'reference' => $reference,
    'objet' => $data['objet'],
    'description' => $data['description'],
    'date_limite_soumission' => $data['date_limite_soumission'],
    'type_publication' => $data['type_publication'],
    'creator_id' => auth()->id(),
    'statut' => 'brouillon',
    'is_plis_ouverts' => false,
]);

foreach ($data['criteres'] as $c) {
    $ao->criteres()->create($c);
}

// publish()
$appelOffre->update(['statut' => 'publie']);
// notifier les fournisseurs</pre>

    <h2>6. Comité + fragments de clé</h2>
    <div class="box">
        <strong>Principe :</strong> on génère une clé maître, on stocke uniquement son hash,
        on découpe la clé en fragments (2 caractères) et on envoie 1 fragment à chaque membre.
        À l'ouverture, on reconstitue la clé dans l'ordre des positions.
    </div>
<pre>$n = count($membres);
$masterKey = strtoupper(Str::random($n * 2)); // ex: A1B2C3D4
$parts = str_split($masterKey, 2);            // A1, B2, C3, D4

$appelOffre->update([
    'cle_ouverture_hash' => Hash::make($masterKey),
    'is_plis_ouverts' => false,
]);

foreach ($membres as $i => $m) {
    // notifier fragment $parts[$i] + position ($i + 1)
}</pre>

    <h2>7. Soumission d'offre</h2>
<pre>abort_unless($appelOffre->statut === 'publie', 403);
abort_if(now()->gt($appelOffre->date_limite_soumission), 422, 'Date limite dépassée.');
$appelOffre->offres()->create($validated);</pre>

    <h2>8. Session d'ouverture</h2>
    <h3>Chargement (anonymat si scellé)</h3>
<pre>if (! $appelOffre->is_plis_ouverts) {
    $query->select('id', 'appel_offre_id', 'created_at'); // pas de nom fournisseur
}</pre>

    <h3>Vérification de la clé</h3>
<pre>$cle = strtoupper(str_replace(' ', '', trim($request->cle_ouverture)));

if (! Hash::check($cle, $appelOffre->cle_ouverture_hash)) {
    return back()->withErrors(['cle_ouverture' => 'Clé incorrecte.']);
}

$appelOffre->update([
    'is_plis_ouverts' => true,
    'statut' => 'en_evaluation',
]);</pre>

    <h3>Règles UI</h3>
    <table>
        <thead><tr><th>Situation</th><th>Comportement</th></tr></thead>
        <tbody>
            <tr><td>Avant date limite</td><td>Bouton désactivé (« Attente de la date limite »)</td></tr>
            <tr><td>Plis scellés</td><td>Offres anonymes (« Offre anonyme #1 »…)</td></tr>
            <tr><td>Clé incorrecte</td><td>Erreur, pas d'ouverture</td></tr>
            <tr><td>Ouverture OK</td><td>Noms visibles + PV téléchargeable</td></tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>9. Front Inertia / Vue (idée)</h2>
<pre>// POST /appel-offres/{id}/start-evaluation
// body: { cle_ouverture: 'A1B2C3D4' }

const isClosed = now > new Date(appelOffre.date_limite_soumission);
const isOpened = appelOffre.is_plis_ouverts;

// bouton "Procéder à l'ouverture officielle"
// disabled si !isClosed || processing
// input: clé reconstituée du comité</pre>

    <h2>10. Ordre d'implémentation</h2>
    <ol>
        <li>Migrations + modèles Eloquent + relations</li>
        <li>CRUD TDR + publication</li>
        <li>Comité + hash + notifications fragments</li>
        <li>Soumission des offres</li>
        <li>Écran OpeningSession + startEvaluation</li>
        <li>PDF PV (barryvdh/laravel-dompdf)</li>
        <li>Policies (président / responsable peut ouvrir)</li>
    </ol>

    <h2>11. Points d'attention</h2>
    <ul>
        <li>Ne jamais stocker la clé en clair — uniquement le hash.</li>
        <li>Respecter l'ordre des fragments (position 1, 2, 3…).</li>
        <li>Ce mécanisme est un scellé métier (accès + anonymat). Pour un vrai chiffrement des fichiers, ajouter AES/RSA.</li>
        <li>Tester : mauvais ordre → échec ; bonne reconstitution → ouverture.</li>
    </ul>

    <h2>12. Checklist de recette</h2>
    <table>
        <thead><tr><th>#</th><th>Test</th><th>Résultat attendu</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Créer un TDR</td><td>Référence TDR-AAAA-NNNN, statut brouillon</td></tr>
            <tr><td>2</td><td>Publier</td><td>Statut publie</td></tr>
            <tr><td>3</td><td>Créer comité (4 membres)</td><td>4 fragments envoyés, hash enregistré</td></tr>
            <tr><td>4</td><td>Soumettre offres</td><td>Offres présentes, anonymes avant ouverture</td></tr>
            <tr><td>5</td><td>Ouvrir avant date limite</td><td>Refusé</td></tr>
            <tr><td>6</td><td>Mauvaise clé</td><td>Erreur</td></tr>
            <tr><td>7</td><td>Bonne clé reconstituée</td><td>Plis ouverts, statut en_evaluation</td></tr>
            <tr><td>8</td><td>PV</td><td>PDF téléchargeable</td></tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top:24px;">Fin du document — guide Laravel TDR / ouverture des plis.</p>
</body>
</html>
