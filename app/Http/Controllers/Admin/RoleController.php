<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.roles.index');
    }

    public function data(Request $request)
    {
        $query = Role::orderBy('name')->withCount('users');

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $filter = $request->input('user_count');
        if ($filter !== null && $filter !== '') {
            if ($filter === '0') {
                $query->having('users_count', '=', 0);
            } elseif ($filter === '1-5') {
                $query->havingBetween('users_count', [1, 5]);
            } elseif ($filter === '6-10') {
                $query->havingBetween('users_count', [6, 10]);
            } elseif ($filter === '11-plus') {
                $query->having('users_count', '>=', 11);
            }
        }

        $roles = $query->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'users_count' => $r->users_count,
            ];
        });
        return response()->json(['data' => $roles]);
    }

    public function create()
    {
        return view('admin.masterdata.roles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if (Role::where('slug', $slug)->exists()) {
            return back()->withErrors(['slug' => 'Slug sudah digunakan'])->withInput();
        }
        DB::beginTransaction();
        try {
            Role::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
            ]);
            DB::commit();
            return redirect()->route('admin.masterdata.roles.index')->with('success', 'Role berhasil dibuat');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['role' => 'Gagal membuat role: '.$e->getMessage()])->withInput();
        }
    }

    public function edit(Role $role)
    {
        return view('admin.masterdata.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'slug' => ['required', 'string', 'max:100', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        DB::beginTransaction();
        try {
            $role->update($validated);
            DB::commit();
            return redirect()->route('admin.masterdata.roles.index')->with('success', 'Role berhasil diperbarui');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['role' => 'Gagal memperbarui role: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(Role $role)
    {
        DB::beginTransaction();
        try {
            $role->delete();
            DB::commit();
            return redirect()->route('admin.masterdata.roles.index')->with('success', 'Role berhasil dihapus');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['role' => 'Gagal menghapus role: '.$e->getMessage()]);
        }
    }
}
