<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Profil;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $departments = Department::with('manager')
            ->orderBy('name')
            ->paginate($perPage);

        return Inertia::render('departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create()
    {
        $profiles = Profil::orderBy('prenom')->orderBy('nom')->get(['id', 'prenom', 'nom', 'email']);

        return Inertia::render('departments/Create', [
            'profiles' => $profiles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'code' => 'required|string|max:50|unique:departments,code',
            'manager_profile_id' => 'nullable|integer|exists:profiles,id',
        ]);

        Department::create($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Département créé avec succès.');
    }

    public function edit(Department $department)
    {
        $profiles = Profil::orderBy('prenom')->orderBy('nom')->get(['id', 'prenom', 'nom', 'email']);

        return Inertia::render('departments/Edit', [
            'department' => $department->load('manager'),
            'profiles' => $profiles,
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'code' => 'required|string|max:50|unique:departments,code,' . $department->id,
            'manager_profile_id' => 'nullable|integer|exists:profiles,id',
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Département mis à jour.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Département supprimé.');
    }
}
