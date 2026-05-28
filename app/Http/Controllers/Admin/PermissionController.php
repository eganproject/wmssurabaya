<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->withCount('users')->get();
        return view('admin.masterdata.permissions.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        $menus = Menu::with('children')->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get();
        $permissions = DB::table('permission_menu')
            ->where('role_id', $role->id)
            ->get()
            ->keyBy('menu_id');

        return view('admin.masterdata.permissions.edit', compact('role','menus','permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $canView   = collect($request->input('can_view', []));
        $canCreate = collect($request->input('can_create', []));
        $canUpdate = collect($request->input('can_update', []));
        $canDelete = collect($request->input('can_delete', []));

        $menuIds = Menu::pluck('id');

        DB::beginTransaction();
        try {
            foreach ($menuIds as $mid) {
                $flags = [
                    'can_view' => $canView->has($mid),
                    'can_create' => $canCreate->has($mid),
                    'can_update' => $canUpdate->has($mid),
                    'can_delete' => $canDelete->has($mid),
                ];

                $exists = DB::table('permission_menu')->where(['role_id' => $role->id, 'menu_id' => $mid])->exists();

                if ($flags['can_view'] || $flags['can_create'] || $flags['can_update'] || $flags['can_delete']) {
                    DB::table('permission_menu')->updateOrInsert(
                        ['role_id' => $role->id, 'menu_id' => $mid],
                        array_merge($flags, ['updated_at' => now(), 'created_at' => now()])
                    );
                } else {
                    if ($exists) {
                        DB::table('permission_menu')->where(['role_id' => $role->id, 'menu_id' => $mid])->delete();
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['permissions' => 'Gagal menyimpan permission: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('admin.masterdata.permissions.edit', $role->id)->with('success', 'Permission berhasil disimpan');
    }
}

