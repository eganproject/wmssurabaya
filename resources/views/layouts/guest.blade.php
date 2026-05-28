<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="relative min-h-screen flex flex-col items-center justify-center p-6" style="min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px;">
            <!-- Silver silhouette background -->
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10" style="position:absolute; inset:0; z-index:-1; pointer-events:none;">
                <div class="absolute inset-0" style="position:absolute; inset:0; background: linear-gradient(135deg, #f4f5f7 0%, #d7d9dd 50%, #c9cdd1 100%);"></div>
                <div class="absolute inset-0 opacity-70" style="position:absolute; inset:0; background-image:
                        radial-gradient(1200px 600px at -10% 10%, rgba(255,255,255,0.55) 0%, rgba(255,255,255,0) 60%),
                        radial-gradient(900px 500px at 110% 0%, rgba(180,180,185,0.35) 0%, rgba(180,180,185,0) 60%),
                        radial-gradient(800px 500px at 50% 110%, rgba(210,210,215,0.45) 0%, rgba(210,210,215,0) 60%);
                "></div>
            </div>

            <!-- Auth card -->
            <div class="w-full sm:max-w-md mt-6 backdrop-blur-md bg-white/70 border border-white/40 shadow-2xl rounded-2xl overflow-hidden" style="
                width:100%; max-width:28rem; margin-top:1.5rem;
                background:rgba(255,255,255,0.78);
                backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
                border-radius:16px; border:1px solid rgba(255,255,255,0.5);
                box-shadow:0 20px 60px rgba(0,0,0,.15);
            ">
                <div class="px-7 py-6" style="padding:24px;">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
