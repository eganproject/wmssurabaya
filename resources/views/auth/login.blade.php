<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/favicon.png') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} | Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0c0f;
            --card: #12141a;
            --ink: #f8fafc;
            --muted: #9aa4b2;
            --accent: #22d3ee;
            --accent-2: #a78bfa;
            --ring: rgba(34, 211, 238, 0.3);
            --shadow: 0 30px 60px rgba(0, 0, 0, 0.45);
            --radius: 22px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Sora', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(700px 400px at 8% -10%, rgba(34, 211, 238, 0.18) 0%, rgba(34, 211, 238, 0) 55%),
                radial-gradient(680px 380px at 100% 10%, rgba(167, 139, 250, 0.18) 0%, rgba(167, 139, 250, 0) 55%),
                var(--bg);
            min-height: 100svh;
        }

        .screen {
            position: relative;
            min-height: 100svh;
            padding: 28px 18px 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ambient {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
            opacity: 0.8;
            animation: float 12s ease-in-out infinite;
        }

        .orb--a {
            width: 160px;
            height: 160px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.4), rgba(34, 211, 238, 0));
            top: -40px;
            left: -20px;
        }

        .orb--b {
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(167, 139, 250, 0.35), rgba(167, 139, 250, 0));
            bottom: -60px;
            right: -40px;
            animation-delay: -4s;
        }

        .orb--c {
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.25), rgba(56, 189, 248, 0));
            top: 30%;
            right: 8%;
            animation-delay: -8s;
        }

        .card {
            width: min(420px, 100%);
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(148, 163, 184, 0.18);
            padding: 26px 22px 24px;
            position: relative;
            z-index: 2;
            animation: rise 0.5s ease-out both;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }

        .logo {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #111827, #1f2937);
            border: 1px solid rgba(148, 163, 184, 0.25);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .logo img {
            width: 70%;
            height: 70%;
            object-fit: contain;
            filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.35));
        }

        .brand h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .brand p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }

        .alert {
            background: rgba(34, 211, 238, 0.12);
            color: #67e8f9;
            border: 1px solid rgba(34, 211, 238, 0.3);
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 12px;
            margin-bottom: 14px;
        }

        .form-row {
            display: grid;
            gap: 8px;
            margin-bottom: 14px;
        }

        label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            font-size: 14px;
            outline: none;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
            background: rgba(15, 23, 42, 0.55);
            color: var(--ink);
        }

        .input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--ring);
        }

        .input.is-invalid {
            border-color: #f87171;
            box-shadow: 0 0 0 4px rgba(248, 113, 113, 0.2);
        }

        .error-text {
            font-size: 12px;
            color: #fca5a5;
        }

        .row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
        }

        .checkbox {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--muted);
        }

        .checkbox input {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
        }

        .link {
            color: var(--accent);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #111827, #0f172a);
            border: 1px solid rgba(34, 211, 238, 0.35);
            box-shadow: 0 12px 24px rgba(2, 132, 199, 0.25);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:active {
            transform: translateY(1px);
            box-shadow: 0 8px 16px rgba(2, 132, 199, 0.22);
        }

        .footer {
            margin-top: 16px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        .footer a {
            color: var(--accent-2);
            text-decoration: none;
            font-weight: 600;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(16px); }
        }

        @media (min-width: 768px) {
            .card { padding: 30px 28px 26px; }
            .brand h1 { font-size: 20px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .card, .orb { animation: none; }
        }
    </style>
</head>
<body>
<div class="screen">
    <div class="ambient">
        <span class="orb orb--a"></span>
        <span class="orb orb--b"></span>
        <span class="orb orb--c"></span>
    </div>

    <main class="card">
        <div class="brand">
            <div class="logo">
                <img src="{{ asset('metronic/media/logos/favicon.png') }}" alt="{{ config('app.name') }}">
            </div>
            <div>
                <h1>Login ke {{ config('app.name') }}</h1>
                <p>Masuk untuk mengelola stok, inbound, outbound, dan opname.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-row">
                <label for="email">Email</label>
                <input
                    id="email"
                    class="input @error('email') is-invalid @enderror"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                >
                @error('email')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input
                    id="password"
                    class="input @error('password') is-invalid @enderror"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >
                @error('password')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="row-between">
                <label class="checkbox">
                    <input type="checkbox" name="remember">
                    Ingat saya
                </label>
            </div>

            <button type="submit" class="submit-btn">Masuk</button>
        </form>
    </main>
</div>
</body>
</html>
