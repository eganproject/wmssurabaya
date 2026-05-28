<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DivisiController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.divisi.index');
    }

    public function data(Request $request)
    {
        $query = Divisi::orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $recordsTotal = Divisi::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'name' => $d->name,
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
        ]);

        DB::beginTransaction();
        try {
            $divisi = Divisi::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'Divisi berhasil dibuat',
                'divisi' => [
                    'id' => $divisi->id,
                    'name' => $divisi->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat divisi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Divisi $divisi)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        DB::beginTransaction();
        try {
            $divisi->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Divisi berhasil diperbarui',
                'divisi' => [
                    'id' => $divisi->id,
                    'name' => $divisi->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui divisi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Divisi $divisi)
    {
        DB::beginTransaction();
        try {
            $divisi->delete();
            DB::commit();
            return response()->json(['message' => 'Divisi berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus divisi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
