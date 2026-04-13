<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        $articles = Article::with(['sousCategorie.categorie.famille', 'typeDepense', 'latestMovement'])
            ->orderBy('description')
            ->get();

        return Inertia::render('Stock/Index', [
            'articles' => $articles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'type' => 'required|in:entree,sortie,correction',
            'quantite' => 'required|integer|min:1',
            'motif' => 'nullable|string|max:255',
            'destinataire' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $article = Article::lockForUpdate()->find($validated['article_id']);

            StockMovement::create([
                'article_id' => $validated['article_id'],
                'type' => $validated['type'],
                'quantite' => $validated['quantite'],
                'motif' => $validated['motif'],
                'destinataire' => $validated['destinataire'] ?? null,
                'user_id' => auth()->id(),
            ]);

            if ($validated['type'] === 'entree') {
                $article->increment('stock_actuel', $validated['quantite']);
            } elseif ($validated['type'] === 'sortie') {
                $article->decrement('stock_actuel', $validated['quantite']);
            } else {
                $article->increment('stock_actuel', $validated['quantite']);
            }
        });

        return back()->with('success', 'Mouvement de stock enregistré.');
    }

    public function movements()
    {
        $movements = StockMovement::with(['article', 'user'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Stock/Movements', [
            'movements' => $movements,
        ]);
    }
}
