<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Famille;
use App\Models\TypeDepense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with(['sousCategorie.categorie.famille', 'typeDepense'])
            ->latest()
            ->paginate(15);

        $familles = Famille::with(['categories.sousCategories'])->orderBy('nom')->get();
        $typeDepenses = TypeDepense::orderBy('nom_depense')->get(['id', 'nom_depense']);

        return Inertia::render('Configuration/Articles/Index', [
            'articles'    => $articles,
            'familles'    => $familles,
            'typeDepenses' => $typeDepenses,
        ]);
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
