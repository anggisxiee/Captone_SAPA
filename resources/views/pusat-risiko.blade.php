@extends('layouts.app')

@section('title', 'Pusat Risiko')
@section('subtitle', 'Pemantauan insiden, tingkat risiko, dan penanganan keamanan')

@section('actions')
    <button
        type="button"
        onclick="document.getElementById('form-insiden').scrollIntoView({ behavior: 'smooth' })"
        class="btn btn-primary text-xs"
    >
        + Tambah Insiden
    </button>
@endsection

@section('content')

    {{-- Notifikasi --}}
    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold mb-1">Terjadi kesalahan:</p>
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Statistik --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
        @foreach ($stat as $item)
            <div class="card p-4">
                <p class="text-xs text-slate-500">{{ $item[0] }}</p>
                <p class="text-2xl font-bold text-slate-800 mt-2">
                    {{ $item[1] }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- Form Tambah Insiden --}}
    <div id="form-insiden" class="card p-4 mb-5">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-800">
                Tambah Insiden Risiko
            </h3>
            <p class="text-xs text-slate-500 mt-1">
                Catat insiden atau kejadian yang berpotensi mengganggu keamanan sistem.
            </p>
        </div>

        <form action="{{ route('pusat-risiko.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="field-label">Nomor WhatsApp</label>
                    <select name="nomor_whatsapp_id" class="field w-full">
                        <option value="">Pilih nomor WhatsApp (opsional)</option>

                        @foreach ($nomorWhatsapp as $nomor)
                            <option
                                value="{{ $nomor->id }}"
                                @selected(old('nomor_whatsapp_id') == $nomor->id)
                            >
                                {{ $nomor->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label">Jenis Insiden</label>
                    <input
                        type="text"
                        name="jenis_insiden"
                        class="field w-full"
                        placeholder="Contoh: Aktivitas pengiriman tidak normal"
                        value="{{ old('jenis_insiden') }}"
                        required
                    >
                </div>

                <div>
                    <label class="field-label">Tingkat Risiko</label>
                    <select name="tingkat_risiko" class="field w-full" required>
                        <option value="">Pilih tingkat risiko</option>
                        @foreach (['Rendah', 'Sedang', 'Tinggi', 'Kritis'] as $tingkat)
                            <option
                                value="{{ $tingkat }}"
                                @selected(old('tingkat_risiko') === $tingkat)
                            >
                                {{ $tingkat }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label">Deskripsi Insiden</label>
                    <textarea
                        name="deskripsi"
                        class="field w-full"
                        rows="3"
                        placeholder="Jelaskan kejadian atau indikasi risiko..."
                        required
                    >{{ old('deskripsi') }}</textarea>
                </div>

            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" class="btn btn-primary text-xs">
                    Simpan Insiden
                </button>
            </div>
        </form>
    </div>

    {{-- Daftar Insiden --}}
    <div class="card p-4">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-800">
                Daftar Insiden Risiko
            </h3>
            <p class="text-xs text-slate-500 mt-1">
                Riwayat insiden yang tercatat dalam sistem.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th class="py-3 px-2">Waktu</th>
                        <th class="py-3 px-2">Jenis Insiden</th>
                        <th class="py-3 px-2">Nomor WhatsApp</th>
                        <th class="py-3 px-2">Tingkat Risiko</th>
                        <th class="py-3 px-2">Deskripsi</th>
                        <th class="py-3 px-2">Status</th>
                        <th class="py-3 px-2">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($insiden as $item)
                        @php
                            $warnaRisiko = match ($item->tingkat_risiko) {
                                'Rendah' => 'bg-emerald-100 text-emerald-700',
                                'Sedang' => 'bg-amber-100 text-amber-700',
                                'Tinggi' => 'bg-orange-100 text-orange-700',
                                'Kritis' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };

                            $warnaStatus = match ($item->status) {
                                'Terbuka' => 'bg-red-100 text-red-700',
                                'Ditangani' => 'bg-amber-100 text-amber-700',
                                'Selesai' => 'bg-emerald-100 text-emerald-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp

                        <tr class="border-b border-slate-100">
                            <td class="py-3 px-2 whitespace-nowrap text-slate-600">
                                {{ $item->terjadi_pada?->format('d/m/Y H:i') ?? '-' }}
                            </td>

                            <td class="py-3 px-2 font-medium text-slate-800">
                                {{ $item->jenis_insiden }}
                            </td>

                            <td class="py-3 px-2 text-slate-600">
                                {{ $item->nomorWhatsapp?->nama ?? '-' }}
                            </td>

                            <td class="py-3 px-2">
                                <span class="px-2 py-1 rounded-full {{ $warnaRisiko }}">
                                    {{ $item->tingkat_risiko }}
                                </span>
                            </td>

                            <td class="py-3 px-2 text-slate-600 max-w-xs">
                                {{ $item->deskripsi }}
                            </td>

                            <td class="py-3 px-2">
                                <span class="px-2 py-1 rounded-full {{ $warnaStatus }}">
                                    {{ $item->status }}
                                </span>
                            </td>

                            <td class="py-3 px-2">
                                <div class="flex flex-col gap-2 min-w-32">

                                    <form
                                        action="{{ route('pusat-risiko.status', $item->id) }}"
                                        method="POST"
                                        class="flex gap-1"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <select
                                            name="status"
                                            class="field text-xs py-1"
                                        >
                                            @foreach (['Terbuka', 'Ditangani', 'Selesai'] as $status)
                                                <option
                                                    value="{{ $status }}"
                                                    @selected($item->status === $status)
                                                >
                                                    {{ $status }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <button
                                            type="submit"
                                            class="btn btn-primary text-xs px-2 py-1"
                                        >
                                            Simpan
                                        </button>
                                    </form>

                                    <form
                                        action="{{ route('pusat-risiko.destroy', $item->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus insiden ini?')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-outline text-xs px-2 py-1"
                                        >
                                            Hapus
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                Belum ada insiden risiko yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
