<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KurirController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.kurir.index');
    }

    public function data(Request $request)
    {
        $query = Kurir::orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $recordsTotal = Kurir::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($kurir) {
            return [
                'id' => $kurir->id,
                'name' => $kurir->name,
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
            'name' => ['required', 'string', 'max:150', 'unique:kurirs,name'],
        ]);

        DB::beginTransaction();
        try {
            $kurir = Kurir::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'Kurir berhasil dibuat',
                'kurir' => [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat kurir',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Kurir $kurir)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('kurirs', 'name')->ignore($kurir->id),
            ],
        ]);

        DB::beginTransaction();
        try {
            $kurir->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Kurir berhasil diperbarui',
                'kurir' => [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui kurir',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Kurir $kurir)
    {
        DB::beginTransaction();
        try {
            $kurir->delete();
            DB::commit();
            return response()->json(['message' => 'Kurir berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus kurir',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
