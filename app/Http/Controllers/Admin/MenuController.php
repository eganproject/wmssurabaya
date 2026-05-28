<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.menus.index');
    }

    public function data(Request $request)
    {
        $query = Menu::with('parent:id,name')->orderBy('sort_order')->orderBy('name');

        if ($status = $request->string('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('route', 'like', "%{$search}%");
            });
        }

        $menus = $query->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'slug' => $m->slug,
                'route' => $m->route,
                'icon' => $m->icon,
                'parent' => $m->parent?->name,
                'sort_order' => $m->sort_order,
                'is_active' => $m->is_active,
            ];
        });
        return response()->json(['data' => $menus]);
    }

    public function create()
    {
        $parents = Menu::orderBy('name')->get();
        return view('admin.masterdata.menus.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:100'],
            'slug' => ['required','string','max:100','unique:menus,slug'],
            'route' => ['nullable','string','max:150'],
            'icon' => ['nullable','string','max:100'],
            'parent_id' => ['nullable','integer','exists:menus,id'],
            'sort_order' => ['nullable','integer'],
            'is_active' => ['nullable','boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        DB::beginTransaction();
        try {
            Menu::create($validated);
            DB::commit();
            return redirect()->route('admin.masterdata.menus.index')->with('success', 'Menu berhasil dibuat');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['menu' => 'Gagal membuat menu: '.$e->getMessage()])->withInput();
        }
    }

    public function edit(Menu $menu)
    {
        $parents = Menu::where('id', '!=', $menu->id)->orderBy('name')->get();
        return view('admin.masterdata.menus.edit', compact('menu','parents'));
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:100'],
            'slug' => ['required','string','max:100', Rule::unique('menus','slug')->ignore($menu->id)],
            'route' => ['nullable','string','max:150'],
            'icon' => ['nullable','string','max:100'],
            'parent_id' => ['nullable','integer','exists:menus,id'],
            'sort_order' => ['nullable','integer'],
            'is_active' => ['nullable','boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        DB::beginTransaction();
        try {
            $menu->update($validated);
            DB::commit();
            return redirect()->route('admin.masterdata.menus.index')->with('success', 'Menu berhasil diperbarui');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['menu' => 'Gagal memperbarui menu: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(Menu $menu)
    {
        DB::beginTransaction();
        try {
            $menu->delete();
            DB::commit();
            return redirect()->route('admin.masterdata.menus.index')->with('success', 'Menu berhasil dihapus');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['menu' => 'Gagal menghapus menu: '.$e->getMessage()]);
        }
    }
}
