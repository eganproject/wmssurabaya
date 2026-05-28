<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Role;
use App\Models\User;
use App\Imports\UsersImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.users.index', compact('roles'));
    }

    public function data(Request $request)
    {
        $query = User::with('roles:id,name', 'divisi:id,name')->orderBy('name');

        if ($roleId = $request->integer('role_id')) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId));
        }

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('divisi', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $users = $query->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'divisi' => $u->divisi?->name ?? '-',
                'roles' => $u->roles->pluck('name')->implode(', '),
            ];
        });

        return response()->json(['data' => $users]);
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        $divisis = Divisi::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.users.create', compact('roles', 'divisis'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6','max:100'],
            'roles' => ['nullable','array'],
            'roles.*' => ['integer','exists:roles,id'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
            'divisi_id' => ['nullable','integer','exists:divisis,id'],
        ]);
        $avatarPath = null;
        $storedAvatar = null;

        DB::beginTransaction();
        try {
            if ($request->file('avatar')) {
                $storedAvatar = $request->file('avatar')->store('avatars', 'public');
                $avatarPath = 'storage/'.$storedAvatar;
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'avatar' => $avatarPath ?: User::defaultAvatar(),
                'email_verified_at' => now(),
                'divisi_id' => $validated['divisi_id'] ?? null,
            ]);
            if (!empty($validated['roles'])) {
                $user->roles()->sync($validated['roles']);
            }

            DB::commit();
            return redirect()->route('admin.masterdata.users.index')->with('success', 'User berhasil dibuat');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedAvatar) {
                Storage::disk('public')->delete($storedAvatar);
            }
            return back()->withErrors(['user' => 'Gagal membuat user: '.$e->getMessage()])->withInput();
        }
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $user->load('roles');
        $divisis = Divisi::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.users.edit', compact('user','roles','divisis'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:6','max:100'],
            'roles' => ['nullable','array'],
            'roles.*' => ['integer','exists:roles,id'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
            'divisi_id' => ['nullable','integer','exists:divisis,id'],
        ]);
        $update = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'divisi_id' => $validated['divisi_id'] ?? null,
        ];
        if (!empty($validated['password'])) {
            $update['password'] = Hash::make($validated['password']);
        }

        $newAvatarPath = null;
        $oldAvatarPath = null;

        DB::beginTransaction();
        try {
            if ($request->file('avatar')) {
                $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
                $update['avatar'] = 'storage/'.$newAvatarPath;
                if ($user->avatar && str_starts_with($user->avatar, 'storage/avatars/')) {
                    $oldAvatarPath = str_replace('storage/', '', $user->avatar);
                }
            }

            $user->update($update);
            $user->roles()->sync($validated['roles'] ?? []);
            DB::commit();

            if ($oldAvatarPath) {
                Storage::disk('public')->delete($oldAvatarPath);
            }

            return redirect()->route('admin.masterdata.users.index')->with('success', 'User berhasil diperbarui');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newAvatarPath) {
                Storage::disk('public')->delete($newAvatarPath);
            }
            return back()->withErrors(['user' => 'Gagal memperbarui user: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(User $user)
    {
        $avatarPath = null;
        if ($user->avatar && str_starts_with($user->avatar, 'storage/avatars/')) {
            $avatarPath = str_replace('storage/', '', $user->avatar);
        }

        DB::beginTransaction();
        try {
            $user->roles()->detach();
            $user->delete();
            DB::commit();

            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            return redirect()->route('admin.masterdata.users.index')->with('success', 'User berhasil dihapus');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['user' => 'Gagal menghapus user: '.$e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $import = new UsersImport();
        Excel::import($import, $validated['file']);

        $rows = $import->rows;
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }

        $roles = Role::query()->get(['id', 'name', 'slug']);
        $roleById = $roles->keyBy(fn ($r) => (string) $r->id);
        $roleBySlug = $roles->keyBy(fn ($r) => strtolower((string) $r->slug));
        $roleByName = $roles->keyBy(fn ($r) => strtolower((string) $r->name));

        $divisis = Divisi::query()->get(['id', 'name']);
        $divisiById = $divisis->keyBy(fn ($d) => (string) $d->id);
        $divisiByName = $divisis->keyBy(fn ($d) => strtolower((string) $d->name));

        $errors = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = $row['row'] ?? '?';
            $email = (string) ($row['email'] ?? '');
            if (User::where('email', $email)->exists()) {
                $errors[] = "Baris {$rowNo}: Email sudah terdaftar ({$email})";
                continue;
            }

            $divisiId = null;
            $divisiRaw = trim((string) ($row['divisi_raw'] ?? ''));
            if ($divisiRaw !== '') {
                if (is_numeric($divisiRaw)) {
                    $divisi = $divisiById->get((string) $divisiRaw);
                } else {
                    $divisi = $divisiByName->get(strtolower($divisiRaw));
                }
                if (!$divisi) {
                    $errors[] = "Baris {$rowNo}: Divisi tidak ditemukan ({$divisiRaw})";
                    continue;
                }
                $divisiId = $divisi->id;
            }

            $roleIds = [];
            $rolesRaw = trim((string) ($row['roles_raw'] ?? ''));
            if ($rolesRaw !== '') {
                $tokens = preg_split('/[;,|]+/', $rolesRaw) ?: [];
                foreach ($tokens as $token) {
                    $token = trim((string) $token);
                    if ($token === '') {
                        continue;
                    }
                    if (is_numeric($token)) {
                        $role = $roleById->get((string) $token);
                    } else {
                        $lower = strtolower($token);
                        $role = $roleBySlug->get($lower) ?? $roleByName->get($lower);
                    }
                    if (!$role) {
                        $errors[] = "Baris {$rowNo}: Role tidak ditemukan ({$token})";
                        continue 2;
                    }
                    $roleIds[] = $role->id;
                }
            }

            $prepared[] = [
                'name' => $row['name'],
                'email' => $email,
                'password' => $row['password'],
                'roles' => array_values(array_unique($roleIds)),
                'divisi_id' => $divisiId,
            ];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        $created = 0;
        DB::beginTransaction();
        try {
            foreach ($prepared as $payload) {
                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => Hash::make((string) $payload['password']),
                    'avatar' => User::defaultAvatar(),
                    'email_verified_at' => now(),
                    'divisi_id' => $payload['divisi_id'],
                ]);
                if (!empty($payload['roles'])) {
                    $user->roles()->sync($payload['roles']);
                }
                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import user',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Import user berhasil',
            'created' => $created,
        ]);
    }
}
