<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · SAPA Asentra</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-sm text-slate-800 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm px-4">
        <div class="text-center mb-5">
            <div class="text-2xl font-bold text-teal-700">SAPA</div>
            <div class="text-xs text-slate-500">Sistem Asisten WhatsApp Bimbel Asentra</div>
        </div>
        <form method="POST" action="{{ route('login.post') }}" class="card space-y-4 p-6">
            @csrf
            <h2 class="text-base font-semibold">Masuk</h2>
            @if (session('error'))
                <div class="rounded-lg border border-red-300 bg-red-50 text-red-600 px-3 py-2 text-xs">{{ session('error') }}</div>
            @endif
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="dewi@asentra.sch.id" class="field" required>
            </div>
            <div>
                <label class="label">Kata sandi</label>
                <input type="password" name="password" class="field" required>
            </div>
            <button class="btn btn-primary w-full">Masuk</button>
            <p class="text-[11px] text-slate-500">Akun dibuat oleh Administrator. Setelah 5 kali gagal, login ditahan 5 menit.</p>
            <p class="text-[11px] text-slate-400">Demo: dewi@asentra.sch.id / password123</p>
        </form>
    </div>
</body>
</html>