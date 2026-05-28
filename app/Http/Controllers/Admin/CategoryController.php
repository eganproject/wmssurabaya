<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index()
    {
        $parents = Category::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.categories.index', compact('parents'));
    }

    public function data(Request $request)
    {
        $query = Category::with('parent')->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $parentFilter = $request->input('parent_id');
        if ($parentFilter !== null && $parentFilter !== '') {
            if ((int) $parentFilter === 0) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $parentFilter);
            }
        }

        $recordsTotal = Category::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'parent' => $c->parent?->name ?? '-',
                'parent_id' => $c->parent_id ?? 0,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Parent tidak valid');
                }
            }],
        ]);

        $parentId = $request->input('parent_id');
        $validated['parent_id'] = ($parentId === null || (int) $parentId === 0) ? 0 : $parentId;

        DB::beginTransaction();
        try {
            $category = Category::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'Kategori berhasil dibuat',
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parent_id' => $category->parent_id,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat kategori',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Parent tidak valid');
                }
            }],
        ]);

        $parentId = $request->input('parent_id');
        $validated['parent_id'] = ($parentId === null || (int) $parentId === 0) ? 0 : $parentId;

        DB::beginTransaction();
        try {
            $category->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Kategori berhasil diperbarui',
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parent_id' => $category->parent_id,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui kategori',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Category $category)
    {
        DB::beginTransaction();
        try {
            $category->delete();
            DB::commit();
            return response()->json(['message' => 'Kategori berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus kategori',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
