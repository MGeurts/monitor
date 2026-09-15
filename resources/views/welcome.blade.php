<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-950 text-white">
        <main class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
            <x-stock-monitor-logo class="size-28 sm:size-32" />
            <h1 class="mt-8 text-3xl font-semibold tracking-tight sm:text-4xl">Stock Monitor</h1>
            <p class="mt-3 text-sm text-slate-300 sm:text-base">Controleer voorraad over al je verkoopkanalen.</p>

            @auth
                <a href="{{ route('dashboard') }}" class="mt-8 inline-flex items-center rounded-lg bg-teal-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-300 focus:ring-offset-2 focus:ring-offset-slate-950">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="mt-8 inline-flex items-center rounded-lg bg-teal-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-300 focus:ring-offset-2 focus:ring-offset-slate-950">LOGIN</a>
            @endauth
        </main>
    </body>
</html>
