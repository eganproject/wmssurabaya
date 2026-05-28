<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="login-url" content="{{ route('login') }}">
        <meta name="page-expired-url" content="{{ route('page-expired') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
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
