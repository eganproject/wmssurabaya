<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockApiAllowedIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockApiAllowedIpController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.stock-api-access.index', [
            'ips' => StockApiAllowedIp::orderByDesc('is_active')->orderBy('ip_address')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ip_address' => ['required', 'ip', 'max:45', 'unique:stock_api_allowed_ips,ip_address'],
            'label' => ['nullable', 'string', 'max:150'],
        ]);
        StockApiAllowedIp::create([...$data, 'is_active' => true]);

        return back()->with('success', 'IP diizinkan untuk mengakses API stok.');
    }

    public function update(Request $request, StockApiAllowedIp $stockApiAllowedIp): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ]);
        $stockApiAllowedIp->update($data);

        return back()->with('success', 'Status akses IP diperbarui.');
    }

    public function destroy(StockApiAllowedIp $stockApiAllowedIp): RedirectResponse
    {
        $stockApiAllowedIp->delete();

        return back()->with('success', 'IP dihapus dari whitelist API.');
    }
}
