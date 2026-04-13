<?php

namespace App\Http\Controllers;

use App\Models\DemandeApprovisionnement;
use App\Models\DemandeApprovisionnementItem;
use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DemandeApprovisionnementController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isResponsableStock = $user->hasRole('responsable_stock') || $user->isSuperAdmin();

        $query = DemandeApprovisionnement::with(['user', 'items.article']);

        if (!$isResponsableStock) {
            $query->where('user_id', '=', $user->id);
        }

        $demandes = $query->latest()->paginate(20);

        return Inertia::render('Stock/Demandes/Index', [
            'demandes' => $demandes,
            'isResponsableStock' => $isResponsableStock,
        ]);
    }

    public function create()
    {
        return Inertia::render('Stock/Demandes/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'motif' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.designation' => 'required|string|max:255',
            'items.*.quantite' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($validated) {
            $demande = DemandeApprovisionnement::create([
                'user_id' => auth()->id(),
                'motif' => $validated['motif'],
                'status' => 'en_attente',
                'date_demande' => now(),
            ]);

            foreach ($validated['items'] as $item) {
                DemandeApprovisionnementItem::create([
                    'demande_id' => $demande->id,
                    'designation' => $item['designation'],
                    'quantite_demandee' => $item['quantite'],
                ]);
            }
        });

        return redirect()->route('demandes-approvisionnement.index')->with('success', 'Demande d\'approvisionnement créée avec succès.');
    }

    public function show(DemandeApprovisionnement $demande)
    {
        $demande->load(['user', 'items.article.sousCategorie.categorie.famille', 'traitee_par_user']);
        
        $user = auth()->user();
        $isResponsableStock = $user->hasRole('responsable_stock') || $user->isSuperAdmin();

        if (!$isResponsableStock && $demande->user_id !== $user->id) {
            abort(403);
        }

        return Inertia::render('Stock/Demandes/Show', [
            'demande' => $demande,
            'isResponsableStock' => $isResponsableStock,
            // Pour le sélecteur en cascade, on charge tous les articles avec leur hiérarchie
            'articles' => $isResponsableStock ? Article::with(['sousCategorie.categorie.famille'])->get() : [],
        ]);
    }

    public function updateStatus(Request $request, DemandeApprovisionnement $demande)
    {
        $validated = $request->validate([
            'status' => 'required|in:approuvee,rejetee,livree',
            'items' => 'nullable|array',
            'items.*.id' => 'required|exists:demande_approvisionnement_items,id',
            'items.*.article_id' => 'nullable|exists:articles,id',
            'items.*.quantite_livree' => 'nullable|integer|min:0',
        ]);

        $user = auth()->user();
        if (!$user->hasRole('responsable_stock') && !$user->isSuperAdmin()) {
            abort(403);
        }

        DB::transaction(function () use ($validated, $demande, $user) {
            $demande->update([
                'status' => $validated['status'],
                'traitee_par' => $user->id,
                'date_traitement' => now(),
            ]);

            if (isset($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $item = DemandeApprovisionnementItem::find($itemData['id']);
                    
                    if ($item) {
                        // Update article mapping and quantities
                        $item->update([
                            'article_id' => $itemData['article_id'] ?? $item->article_id,
                            'quantite_livree' => $itemData['quantite_livree'] ?? 0,
                        ]);

                        // If delivered, decrement stock
                        if ($validated['status'] === 'livree' && ($itemData['quantite_livree'] ?? 0) > 0) {
                            $articleId = $itemData['article_id'] ?? $item->article_id;
                            if (!$articleId) continue;

                            $article = Article::lockForUpdate()->find($articleId);
                            if ($article) {
                                $article->decrement('stock_actuel', $itemData['quantite_livree']);

                                StockMovement::create([
                                    'article_id' => $articleId,
                                    'type' => 'sortie',
                                    'quantite' => $itemData['quantite_livree'],
                                    'motif' => 'Livraison demande appro n°' . $demande->id,
                                    'destinataire' => $demande->user->name,
                                    'user_id' => $user->id,
                                ]);
                            }
                        }
                    }
                }
            }
        });

        return back()->with('success', 'Statut de la demande mis à jour.');
    }

    public function update(Request $request, DemandeApprovisionnement $demande)
    {
        return $this->updateStatus($request, $demande);
    }
}
