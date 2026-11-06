
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · SAPA Asentra</title>

    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 text-xs font-sans antialiased">

@php
    // Mengambil role pengguna yang sedang login
    $role = $penggunaLogin->role ?? '';

    // Menu untuk semua pengguna
    $menu = [
        'dasbor' => 'Dasbor',
        'kotak-masuk' => 'Kotak Masuk',
        'prospek' => 'Prospek',
        'kontak' => 'Kontak',
    ];

    // Menu khusus Supervisor dan Administrator
    if (in_array($role, ['Supervisor', 'Administrator'])) {
        $menu['nomor-whatsapp'] = 'Nomor WhatsApp';
    }

    // Menu untuk semua pengguna
    $menu['basis-pengetahuan'] = 'Basis Pengetahuan';

    // Menu khusus Supervisor dan Administrator
    if (in_array($role, ['Supervisor', 'Administrator'])) {
        $menu['pengaturan-ai'] = 'Pengaturan AI';
        $menu['antrean-risiko'] = 'Antrean & Risiko';
    }

    // Menu khusus Administrator
    if ($role === 'Administrator') {
        $menu['tim-akses'] = 'Tim & Akses';
    }

    // Menu khusus Supervisor dan Administrator
    if (in_array($role, ['Supervisor', 'Administrator'])) {
        $menu['log-audit'] = 'Log Audit';
    }
@endphp

<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="w-40 shrink-0 bg-white border-r border-slate-200 flex flex-col h-screen sticky top-0">

        {{-- Logo --}}
        <div class="px-4 py-4">
            <div class="text-base font-bold text-teal-700">SAPA</div>
            <div class="text-[10px] text-slate-500">Bimbel Asentra</div>
        </div>

        {{-- Menu Navigasi --}}
        <nav class="flex-1 px-2.5 space-y-0.5 overflow-y-auto">

            @foreach ($menu as $route => $label)
                <a href="{{ route($route) }}"
                   class="block rounded-md px-2.5 py-1.5 text-[11px]
                   {{ request()->routeIs($route)
                        ? 'bg-teal-50 text-teal-700 font-medium'
                        : 'text-slate-600 hover:bg-slate-50' }}">

                    {{ $label }}

                </a>
            @endforeach

        </nav>

        {{-- Profil Pengguna Login --}}
        <div class="border-t border-slate-200 px-4 py-3">

            <div class="text-[11px] font-medium text-slate-800 truncate">
                {{ $penggunaLogin->name ?? 'Pengguna' }}
            </div>

            <div class="text-[10px] text-slate-500 truncate">
                {{ $penggunaLogin->role ?? '-' }}
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf

                <button type="submit"
                    class="text-[10px] text-slate-500 hover:text-teal-700 cursor-pointer">
                    Keluar
                </button>
            </form>

        </div>
    </aside>

    {{-- Konten Utama --}}
    <main class="flex-1 min-w-0 p-5">

        {{-- Header Halaman --}}
        <header class="flex items-start justify-between mb-4">

            <div>
                <h1 class="text-lg font-semibold">
                    @yield('title')
                </h1>

                <p class="text-[10px] text-slate-500">
                    @yield('subtitle')
                </p>
            </div>

            <div class="flex items-center gap-2">
                @yield('actions')
            </div>

        </header>

        {{-- Notifikasi Berhasil --}}
        @if (session('success'))
            <div class="mb-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-[10px] text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Notifikasi Error --}}
        @if (session('error'))
            <div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[10px] text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Isi Halaman --}}
        @yield('content')

    </main>

</div>

</body>
</html>
