<?php

namespace App\Http\Controllers;

use App\Models\CategorieDepense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategorieDepenseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $categories = CategorieDepense::orderBy('categorie')
            ->paginate($perPage);

        return Inertia::render('categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'categorie' => 'required|string|max:255|unique:categorie_depenses,categorie',
            'code' => 'required|string|max:50|unique:categorie_depenses,code',
        ]);

        CategorieDepense::create($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie créée avec succès.');
    }

    public function edit(CategorieDepense $category)
    {
        return Inertia::render('categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, CategorieDepense $category)
    {
        $validated = $request->validate([
            'categorie' => 'required|string|max:255|unique:categorie_depenses,categorie,' . $category->id,
            'code' => 'required|string|max:50|unique:categorie_depenses,code,' . $category->id,
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(CategorieDepense $category)
    {
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Catégorie supprimée.');
    }
}
