<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Famille;
use App\Models\SousCategorie;
use App\Models\TypeDepense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $q = trim((string) $request->input('q', ''));
        $responsable = trim((string) $request->input('responsable', ''));
        $familleId = $request->integer('famille_id') ?: null;
        $typeDepenseId = $request->integer('type_depense_id') ?: null;

        $articles = Article::query()
            ->with(['sousCategorie.categorie.famille', 'typeDepense'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'ilike', "%{$q}%")
                        ->orWhere('description', 'ilike', "%{$q}%");
                });
            })
            ->when($responsable !== '' && in_array($responsable, ['IT', 'Facilities', 'RH', 'ALL'], true), function ($query) use ($responsable) {
                $query->where('responsable', $responsable);
            })
            ->when($familleId, function ($query) use ($familleId) {
                $query->whereHas('sousCategorie.categorie', fn ($q) => $q->where('famille_id', $familleId));
            })
            ->when($typeDepenseId, function ($query) use ($typeDepenseId) {
                $query->where('type_depense_id', $typeDepenseId);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $familles = Famille::with(['categories.sousCategories'])->orderBy('nom')->get();
        $typeDepenses = TypeDepense::orderBy('nom_depense')->get(['id', 'nom_depense']);

        return Inertia::render('Configuration/Articles/Index', [
            'articles'    => $articles,
            'familles'    => $familles,
            'typeDepenses' => $typeDepenses,
            'filters' => [
                'q' => $q,
                'responsable' => $responsable,
                'famille_id' => $familleId,
                'type_depense_id' => $typeDepenseId,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function exportTemplate()
    {
        $filename = 'modele_articles_'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code',
                'Description',
                'Responsable Dépenses',
                'Famille',
                'Catégorie',
                'Sous-catégorie',
                'Type de dépense',
                'Stock actuel',
                'Seuil alerte',
            ]);

            // Ligne d’exemple (commentée par le fait qu’elle peut être adaptée / supprimée)
            fputcsv($handle, [
                'EXEMPLE-001',
                'Article exemple — à supprimer ou adapter',
                'IT',
                '',
                '',
                '',
                '',
                '0',
                '5',
            ]);

            Article::with(['sousCategorie.categorie.famille', 'typeDepense'])
                ->orderBy('code')
                ->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $article) {
                        fputcsv($handle, [
                            $article->code,
                            $article->description,
                            $article->responsable,
                            $article->sousCategorie?->categorie?->famille?->nom ?? '',
                            $article->sousCategorie?->categorie?->nom ?? '',
                            $article->sousCategorie?->nom ?? '',
                            $article->typeDepense?->nom_depense ?? '',
                            $article->stock_actuel ?? 0,
                            $article->seuil_alerte ?? 5,
                        ]);
                    }
                });

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
        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), str_getcsv($firstLine, $delimiter));

        $col = function (string ...$names) use ($headers): ?int {
            foreach ($names as $name) {
                $idx = array_search(mb_strtolower($name), $headers, true);
                if ($idx !== false) {
                    return (int) $idx;
                }
            }

            return null;
        };

        $idxCode = $col('code');
        $idxDescription = $col('description', 'libellé', 'libelle');
        $idxResponsable = $col('responsable dépenses', 'responsable depenses', 'responsable');
        $idxFamille = $col('famille');
        $idxCategorie = $col('catégorie', 'categorie');
        $idxSousCategorie = $col('sous-catégorie', 'sous-categorie', 'sous catégorie');
        $idxType = $col('type de dépense', 'type depense', 'type_depense');
        $idxStock = $col('stock actuel', 'stock_actuel', 'stock');
        $idxSeuil = $col('seuil alerte', 'seuil_alerte', 'seuil');

        if ($idxCode === null || $idxDescription === null) {
            fclose($handle);

            return back()->with('error', 'Colonnes obligatoires manquantes : Code, Description.');
        }

        $typesByName = TypeDepense::query()
            ->get()
            ->keyBy(fn ($t) => mb_strtolower(trim((string) $t->nom_depense)));

        $sousCategories = SousCategorie::with('categorie.famille')->get();
        $sousByFullKey = [];
        $sousByNameCount = [];
        $sousByNameId = [];
        foreach ($sousCategories as $sc) {
            $fam = mb_strtolower(trim((string) ($sc->categorie?->famille?->nom ?? '')));
            $cat = mb_strtolower(trim((string) ($sc->categorie?->nom ?? '')));
            $sous = mb_strtolower(trim((string) $sc->nom));
            $sousByFullKey["{$fam}|{$cat}|{$sous}"] = $sc->id;
            $sousByNameCount[$sous] = ($sousByNameCount[$sous] ?? 0) + 1;
            $sousByNameId[$sous] = $sc->id;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;
        $validResponsables = ['IT', 'Facilities', 'RH', 'ALL'];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNum++;
                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $get = function (?int $i) use ($row): string {
                    if ($i === null || ! array_key_exists($i, $row)) {
                        return '';
                    }

                    return trim((string) $row[$i]);
                };

                $code = $get($idxCode);
                $description = $get($idxDescription);

                if ($code === '' || $description === '') {
                    $skipped++;
                    continue;
                }

                if (str_starts_with(mb_strtoupper($code), 'EXEMPLE')) {
                    $skipped++;
                    continue;
                }

                $responsable = $get($idxResponsable);
                if ($responsable === '' || $responsable === '-') {
                    $responsable = 'ALL';
                }
                if (! in_array($responsable, $validResponsables, true)) {
                    $errors[] = "Ligne {$rowNum} : responsable « {$responsable} » invalide (IT, Facilities, RH, ALL).";
                    $skipped++;
                    continue;
                }

                $famille = mb_strtolower($get($idxFamille));
                $categorie = mb_strtolower($get($idxCategorie));
                $sousNom = mb_strtolower($get($idxSousCategorie));
                $sousCategorieId = null;
                if ($sousNom !== '') {
                    $fullKey = "{$famille}|{$categorie}|{$sousNom}";
                    if (isset($sousByFullKey[$fullKey])) {
                        $sousCategorieId = $sousByFullKey[$fullKey];
                    } elseif (($sousByNameCount[$sousNom] ?? 0) === 1) {
                        $sousCategorieId = $sousByNameId[$sousNom];
                    } else {
                        $errors[] = "Ligne {$rowNum} : sous-catégorie introuvable ou ambiguë pour « {$code} ».";
                    }
                }

                $typeName = $get($idxType);
                $typeDepenseId = null;
                if ($typeName !== '' && $typeName !== '-') {
                    $typeDepenseId = $typesByName->get(mb_strtolower($typeName))?->id;
                    if ($typeDepenseId === null) {
                        $errors[] = "Ligne {$rowNum} : type de dépense « {$typeName} » introuvable.";
                    }
                }

                $stock = (int) preg_replace('/[^\d\-]/', '', $get($idxStock) ?: '0');
                $seuil = (int) preg_replace('/[^\d\-]/', '', $get($idxSeuil) !== '' ? $get($idxSeuil) : '5');
                if ($stock < 0) {
                    $stock = 0;
                }
                if ($seuil < 0) {
                    $seuil = 0;
                }

                $payload = [
                    'description' => Str::limit($description, 255, ''),
                    'responsable' => $responsable,
                    'sous_categorie_id' => $sousCategorieId,
                    'type_depense_id' => $typeDepenseId,
                    'stock_actuel' => $stock,
                    'seuil_alerte' => $seuil,
                ];

                $existing = Article::query()->where('code', $code)->first();
                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    Article::create(array_merge($payload, [
                        'code' => Str::limit($code, 255, ''),
                    ]));
                    $created++;
                }
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

        $msg = "Import articles : {$created} créé(s), {$updated} mis à jour";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ignoré(s)";
        }
        $msg .= '.';

        $redirect = back()->with('success', $msg);
        if ($errors !== []) {
            $redirect->with('warning', implode(' ', array_slice($errors, 0, 5)));
        }

        return $redirect;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description'      => 'required|string|max:255',
            'code'             => 'required|string|max:255|unique:articles,code',
            'responsable'      => 'required|in:IT,Facilities,RH,ALL',
            'sous_categorie_id' => 'nullable|exists:sous_categories,id',
            'type_depense_id'  => 'nullable|exists:type_depenses,id',
            'stock_actuel'     => 'nullable|integer|min:0',
            'seuil_alerte'     => 'nullable|integer|min:0',
        ]);

        Article::create($validated);
        return redirect()->back()->with('success', 'Article créé avec succès.');
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'description'      => 'required|string|max:255',
            'code'             => 'required|string|max:255|unique:articles,code,' . $article->id,
            'responsable'      => 'required|in:IT,Facilities,RH,ALL',
            'sous_categorie_id' => 'nullable|exists:sous_categories,id',
            'type_depense_id'  => 'nullable|exists:type_depenses,id',
            'stock_actuel'     => 'nullable|integer|min:0',
            'seuil_alerte'     => 'nullable|integer|min:0',
        ]);

        $article->update($validated);
        return redirect()->back()->with('success', 'Article mis à jour avec succès.');
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return redirect()->back()->with('success', 'Article supprimé avec succès.');
    }
}
