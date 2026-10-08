<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\CoficarteApporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApporteurAffaireController extends Controller
{
    public function index()
    {
        $apporteurs = CoficarteApporteur::query()
            ->whereNull('agence_id')
            ->orderBy('nom')
            ->paginate(15)
            ->through(fn (CoficarteApporteur $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'nom' => $a->nom,
                'actif' => (bool) $a->actif,
            ]);

        return Inertia::render('Configuration/ApporteursAffaires/Index', [
            'apporteurs' => $apporteurs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('coficarte_apporteurs', 'code')],
            'nom' => 'required|string|max:191',
        ]);

        CoficarteApporteur::create([
            'agence_id' => null,
            'code' => $validated['code'],
            'nom' => $validated['nom'],
            'telephone' => null,
            'email' => null,
            'actif' => true,
        ]);

        return redirect()->back()->with('success', 'Apporteur d’affaires créé.');
    }

    public function update(Request $request, CoficarteApporteur $coficarteApporteur)
    {
        if ($coficarteApporteur->agence_id !== null) {
            abort(404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('coficarte_apporteurs', 'code')->ignore($coficarteApporteur->id),
            ],
            'nom' => 'required|string|max:191',
        ]);

        $coficarteApporteur->update([
            ...$validated,
            'actif' => $request->boolean('actif'),
        ]);

        return redirect()->back()->with('success', 'Apporteur d’affaires mis à jour.');
    }

    public function destroy(CoficarteApporteur $coficarteApporteur)
    {
        if ($coficarteApporteur->agence_id !== null) {
            abort(404);
        }

        $coficarteApporteur->update(['actif' => false]);

        return redirect()->back()->with('success', 'Apporteur d’affaires désactivé.');
    }

    public function exportTemplate()
    {
        $filename = 'modele_apporteurs_affaires.csv';

        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Nom'], ';');
            fputcsv($handle, ['EXEMPLE-001', 'Nom de l’apporteur — à supprimer ou adapter'], ';');
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Choisissez un fichier CSV à importer.',
            'file.mimes' => 'Le fichier doit être un CSV.',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path ?: '', 'r');
        if ($handle === false) {
            return back()->with('error', 'Impossible de lire le fichier importé.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return back()->with('error', 'Le fichier CSV est vide.');
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $headers = array_map(
            fn ($h) => mb_strtolower(trim((string) $h)),
            str_getcsv($firstLine, $delimiter),
        );

        $indexOf = function (string ...$names) use ($headers): ?int {
            foreach ($names as $name) {
                $idx = array_search(mb_strtolower($name), $headers, true);
                if ($idx !== false) {
                    return (int) $idx;
                }
            }

            return null;
        };

        $idxCode = $indexOf('code');
        $idxNom = $indexOf('nom', 'name');
        $idxActif = $indexOf('actif', 'statut');

        if ($idxCode === null || $idxNom === null) {
            fclose($handle);

            return back()->with('error', 'Colonnes obligatoires manquantes : Code, Nom.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNum++;
                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $cell = function (?int $i) use ($row): string {
                    if ($i === null || ! array_key_exists($i, $row)) {
                        return '';
                    }

                    return trim((string) $row[$i]);
                };

                $code = mb_strtoupper($cell($idxCode));
                $nom = $cell($idxNom);

                if ($code === '' || $nom === '' || str_starts_with($code, 'EXEMPLE')) {
                    $skipped++;
                    continue;
                }

                if (mb_strlen($code) > 64 || mb_strlen($nom) > 191) {
                    $errors[] = "Ligne {$rowNum} : code ou nom trop long.";
                    $skipped++;
                    continue;
                }

                $existing = CoficarteApporteur::query()
                    ->whereRaw('upper(code) = ?', [$code])
                    ->first();
                if ($existing && $existing->agence_id !== null) {
                    $errors[] = "Ligne {$rowNum} : le code {$code} est déjà utilisé hors du référentiel national.";
                    $skipped++;
                    continue;
                }

                $actif = $idxActif === null ? null : $this->parseActif($cell($idxActif));

                if ($existing) {
                    $payload = ['nom' => $nom];
                    if ($actif !== null) {
                        $payload['actif'] = $actif;
                    }
                    $existing->update($payload);
                    $updated++;
                    continue;
                }

                CoficarteApporteur::create([
                    'agence_id' => null,
                    'code' => $code,
                    'nom' => $nom,
                    'telephone' => null,
                    'email' => null,
                    'actif' => $actif ?? true,
                ]);
                $created++;
            }

            fclose($handle);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            report($e);

            return back()->with('error', 'Import échoué : '.$e->getMessage());
        }

        $message = "Import apporteurs : {$created} créé(s), {$updated} mis à jour";
        if ($skipped > 0) {
            $message .= ", {$skipped} ignoré(s)";
        }
        $message .= '.';

        $redirect = back()->with('success', $message);
        if ($errors !== []) {
            $redirect->with('warning', implode(' ', array_slice($errors, 0, 5)));
        }

        return $redirect;
    }

    private function parseActif(string $value): ?bool
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'oui', 'o', 'actif', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'non', 'n', 'inactif', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }
}
