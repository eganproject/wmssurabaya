<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <meta name="page-expired-url" content="{{ route('page-expired') }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --bg: #f5f3ef;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --brand: #0f766e;
            --accent: #f97316;
            --border: #e5e7eb;
            --shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            --radius: 18px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text);
            background: radial-gradient(1200px 600px at 10% -20%, #fff7ed 0%, rgba(255, 247, 237, 0) 55%),
                        radial-gradient(900px 500px at 100% 0%, #ecfdf3 0%, rgba(236, 253, 243, 0) 55%),
                        var(--bg);
            min-height: 100vh;
        }

        .screen {
            max-width: 520px;
            margin: 0 auto;
            padding: 20px 18px 120px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .subtitle {
            font-size: 12px;
            color: var(--muted);
        }

        .logout {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(226, 232, 240, 0.7);
        }

        .session-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.12);
            color: var(--brand);
            font-weight: 600;
            font-size: 12px;
        }

        .code {
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.4px;
        }

        .muted {
            color: var(--muted);
            font-size: 12px;
        }

        .primary-btn,
        .ghost-btn {
            width: 100%;
            border-radius: 14px;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            border: none;
        }

        .primary-btn {
            background: var(--brand);
            color: #fff;
        }

        .ghost-btn {
            background: #fff;
            border: 1px dashed var(--border);
            color: var(--text);
        }

        .input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-size: 14px;
        }

        .results {
            margin-top: 10px;
            display: grid;
            gap: 10px;
        }

        .result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
        }

        .result-info {
            min-width: 0;
        }

        .result-info strong {
            display: block;
            font-size: 13px;
        }

        .result-info span {
            font-size: 12px;
            color: var(--muted);
        }

        .add-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 12px;
        }

        .items-list {
            display: grid;
            gap: 12px;
        }

        .item-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 12px;
            background: #fff;
        }

        .item-meta strong {
            display: block;
            font-size: 13px;
        }

        .item-meta span {
            font-size: 12px;
            color: var(--muted);
        }

        .qty-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            font-weight: 700;
        }

        .qty-input {
            width: 56px;
            text-align: center;
            padding: 6px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-weight: 600;
        }

        .remove-btn {
            border: none;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 8px;
            padding: 6px 8px;
            font-weight: 700;
        }

        .bottom-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
            border-top: 1px solid var(--border);
            padding: 12px 18px 20px;
            box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.08);
        }

        .bottom-inner {
            max-width: 520px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .summary {
            font-size: 12px;
            color: var(--muted);
        }

        .summary strong {
            display: block;
            color: var(--text);
            font-size: 14px;
        }

        .save-status {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        .disabled {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    @yield('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            if (window.__pageExpiredRedirectPatched) return;
            const pageExpiredUrl = document.querySelector('meta[name="page-expired-url"]')?.getAttribute('content') || '/page-expired';
            const originalFetch = window.fetch?.bind(window);
            if (originalFetch) {
                window.fetch = async (...args) => {
                    const response = await originalFetch(...args);
                    if (response.status === 419) {
                        window.location.href = pageExpiredUrl;
                    }
                    return response;
                };
            }
            window.__pageExpiredRedirectPatched = true;
        })();
    </script>
</body>
</html>
