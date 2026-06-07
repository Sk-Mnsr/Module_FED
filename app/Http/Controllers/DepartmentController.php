<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
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
        return Inertia::render('departments/Create', [
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'code' => 'required|string|max:50|unique:departments,code',
            'manager_user_id' => 'nullable|integer|exists:users,id',
        ]);

        Department::create($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Département créé avec succès.');
    }

    public function edit(Department $department)
    {
        return Inertia::render('departments/Edit', [
            'department' => $department->load('manager'),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,'.$department->id,
            'code' => 'required|string|max:50|unique:departments,code,'.$department->id,
            'manager_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Département mis à jour avec succès.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Département supprimé.');
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function managerOptions(): array
    {
        return User::query()
            ->where('activated', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }
}
