@php
    use App\Models\Menu as MenuModel;
    use App\Support\Permission as Perm;
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $targetUrl = route('login');
    $targetLabel = 'Login ulang';

    if ($user) {
        $roles = $user->roles()->pluck('slug');
        $hasPicker = $roles->contains('picker');
        $hasPacker = $roles->contains('packer');
        $hasAdminScan = $roles->contains('admin-scan');
        $hasOnlyMobileRole = $roles->diff(['picker', 'packer', 'admin-scan'])->isEmpty();

        if ($hasPicker && $hasOnlyMobileRole && Route::has('admin.outbound.qc-scan.index')) {
            $targetUrl = route('admin.outbound.qc-scan.index');
            $targetLabel = 'Kembali ke QC Scan';
        } elseif (($hasPacker || $hasAdminScan) && $hasOnlyMobileRole && Route::has('admin.outbound.scan-out.index')) {
            $targetUrl = route('admin.outbound.scan-out.index');
            $targetLabel = 'Kembali ke Scan Out';
        } else {
            $allowedMenuIds = Perm::viewableMenuIds($user);
            $menu = MenuModel::query()
                ->whereIn('id', $allowedMenuIds)
                ->where('is_active', true)
                ->whereNotNull('route')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->first(fn ($menu) => Route::has($menu->route));

            if ($menu) {
                $targetUrl = route($menu->route);
                $targetLabel = 'Kembali ke '.$menu->name;
            } elseif (Route::has('admin.dashboard')) {
                $targetUrl = route('admin.dashboard');
                $targetLabel = 'Kembali ke Dashboard';
            }
        }
    }
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman Kedaluwarsa</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #64748b;
            --primary: #1b84ff;
            --primary-dark: #056ee9;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .card {
            width: min(100%, 460px);
            padding: 32px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .code {
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 10px;
            border-radius: 6px;
            background: #eef6ff;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 26px;
            line-height: 1.2;
            font-weight: 750;
            letter-spacing: 0;
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.65;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 6px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-light {
            background: #fff;
            color: var(--text);
            border-color: var(--border);
        }

        .btn-light:hover {
            background: #f8fafc;
        }

        @media (max-width: 480px) {
            .card {
                padding: 24px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="code">419 Page Expired</div>
        <h1>Sesi halaman kedaluwarsa</h1>
        <p>
            Halaman ini sudah terlalu lama terbuka atau token keamanan berubah.
            Silakan kembali ke halaman yang tersedia untuk akun Anda, lalu ulangi aksi terakhir.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ $targetUrl }}">{{ $targetLabel }}</a>
            <button class="btn btn-light" type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js($targetUrl))">
                Kembali sebelumnya
            </button>
        </div>
    </main>
</body>
</html>
