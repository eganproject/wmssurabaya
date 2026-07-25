<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UomController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.uoms.index');
    }

    public function data(Request $request)
    {
        $query = Uom::withCount('itemUnits')->orderBy('code');
        if ($q = trim((string) $request->input('q'))) {
            $query->where(fn ($x) => $x->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
        }
        $total = Uom::count();
        $filtered = (clone $query)->count();
        $length = (int) $request->input('length', 10);
        if ($length > 0) $query->skip((int) $request->input('start', 0))->take($length);
        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $uom = Uom::create($this->validated($request));
        return response()->json(['message' => 'UOM berhasil dibuat', 'uom' => $uom]);
    }

    public function update(Request $request, Uom $uom)
    {
        $uom->update($this->validated($request, $uom));
        return response()->json(['message' => 'UOM berhasil diperbarui', 'uom' => $uom->fresh()]);
    }

    public function destroy(Uom $uom)
    {
        if ($uom->itemUnits()->exists()) {
            throw ValidationException::withMessages(['uom' => 'UOM sudah digunakan oleh item dan tidak dapat dihapus. Nonaktifkan UOM bila tidak lagi dipakai.']);
        }
        $uom->delete();
        return response()->json(['message' => 'UOM berhasil dihapus']);
    }

    private function validated(Request $request, ?Uom $uom = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('uoms', 'code')->ignore($uom?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
