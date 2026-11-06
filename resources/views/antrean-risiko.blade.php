
@extends('layouts.app')

@section('title', 'Antrean dan Risiko')
@section('subtitle', 'Pesan keluar, keputusan anti-ban, dan insiden nomor')

@section('actions')
    <span class="text-[10px] text-slate-500">
        Data antrean dari database
    </span>
@endsection

@section('content')
<div class="space-y-3 text-xs"
     x-data="{ bukaModalInsiden: {{ $errors->any() ? 'true' : 'false' }} }">

    {{-- Notifikasi Validasi --}}
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[10px] text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Statistik Antrean --}}
    <div>
        <div class="font-semibold text-xs mb-2">
            Statistik antrean pesan
        </div>

        <div class="grid grid-cols-4 gap-3">
            @foreach ($stat as [$l, $v, $k])
                <div class="card !p-3">
                    <div class="text-[10px] text-slate-500">
                        {{ $l }}
                    </div>
                    <div class="text-lg font-semibold mt-1">
                        {{ $v }}
                    </div>
                    <div class="text-[9px] text-slate-400 mt-0.5">
                        {{ $k }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Statistik Insiden Risiko --}}
    <div>
        <div class="font-semibold text-xs mb-2">
            Statistik insiden risiko
        </div>

        <div class="grid grid-cols-4 gap-3">
            @foreach ($statInsiden as [$l, $v])
                <div class="card !p-3">
                    <div class="text-[10px] text-slate-500">
                        {{ $l }}
                    </div>
                    <div class="text-lg font-semibold mt-1">
                        {{ $v }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Antrean Pesan --}}
    <div class="card !p-3">
        <div class="font-semibold text-xs mb-2">
            Antrean pesan keluar
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-fixed">
                <colgroup>
                    <col style="width: 9%">
                    <col style="width: 10%">
                    <col style="width: 11%">
                    <col style="width: 23%">
                    <col style="width: 8%">
                    <col style="width: 12%">
                    <col style="width: 7%">
                    <col style="width: 20%">
                </colgroup>

                <thead class="bg-slate-50">
                    <tr>
                        @foreach ([
                            'Jadwal',
                            'Pengirim',
                            'Penerima',
                            'Cuplikan pesan',
                            'Skor',
                            'Keputusan',
                            'Coba',
                            'Ubah status'
                        ] as $h)
                            <th class="th !text-[9px] !px-1.5 !py-2">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($antrean as $item)
                        <tr>
                            <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                                {{ $item['jam'] }}
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                                {{ $item['pengirim'] }}
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                                {{ $item['penerima'] }}
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2 text-slate-600 break-words">
                                {{ $item['isi'] }}
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2">
                                {{ $item['skor'] }} / 100
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2">
                                <span class="badge {{ $item['warna'] }} !text-[9px] !px-1.5 !py-0.5">
                                    {{ $item['keputusan'] }}
                                </span>
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2">
                                {{ $item['coba'] }}
                            </td>

                            <td class="td !text-[9px] !px-1.5 !py-2">
                                <form
                                    action="{{ route('antrean-risiko.status', $item['id']) }}"
                                    method="POST"
                                    class="flex flex-col gap-1"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <select
                                        name="keputusan"
                                        class="field w-full !text-[9px] !px-1 !py-1"
                                        required
                                    >
                                        @foreach ([
                                            'Kirim',
                                            'Ditunda',
                                            'Ditahan',
                                            'Gagal'
                                        ] as $status)
                                            <option
                                                value="{{ $status }}"
                                                @selected($item['keputusan'] === $status)
                                            >
                                                {{ $status }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-primary !text-[9px] !px-2 !py-1 w-full"
                                    >
                                        Simpan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8"
                                class="td text-center !py-6 text-slate-400">
                                Belum ada pesan dalam antrean.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pusat Risiko --}}
    <div class="card !p-3">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div>
                <div class="font-semibold text-xs">
                    Pusat risiko
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    Pemantauan insiden, tingkat risiko, dan penanganan keamanan.
                </div>
            </div>

            <button
                type="button"
                @click="bukaModalInsiden = true"
                class="btn btn-primary !text-[10px] !px-3 !py-2 whitespace-nowrap"
            >
                + Tambah Insiden
            </button>
        </div>

        {{-- Tabel Pusat Risiko --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach ([
                            'Waktu',
                            'Nomor WhatsApp',
                            'Jenis Insiden',
                            'Tingkat Risiko',
                            'Deskripsi',
                            'Status',
                            'Aksi'
                        ] as $h)
                            <th class="th !text-[9px] !px-2 !py-2">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($insiden as $item)
                        <tr>
                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $item->terjadi_pada?->format('d/m/Y H:i') ?? '-' }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $item->nomorWhatsapp?->nama ?? 'Tidak ditentukan' }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $item->jenis_insiden }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                @php
                                    $warnaRisiko = match ($item->tingkat_risiko) {
                                        'Rendah' => 'b-green',
                                        'Sedang' => 'b-amber',
                                        'Tinggi', 'Kritis' => 'b-red',
                                        default => 'b-amber',
                                    };
                                @endphp

                                <span class="badge {{ $warnaRisiko }} !text-[9px] !px-1.5 !py-0.5">
                                    {{ $item->tingkat_risiko }}
                                </span>
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2 text-slate-600">
                                {{ $item->deskripsi }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $item->status }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                <div class="flex flex-col gap-1">

                                    {{-- Ubah Status --}}
                                    <form
                                        action="{{ route('pusat-risiko.status', $item->id) }}"
                                        method="POST"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <select
                                            name="status"
                                            class="field w-full !text-[9px] !px-1 !py-1"
                                            onchange="this.form.submit()"
                                        >
                                            @foreach ([
                                                'Terbuka',
                                                'Ditangani',
                                                'Selesai'
                                            ] as $status)
                                                <option
                                                    value="{{ $status }}"
                                                    @selected($item->status === $status)
                                                >
                                                    {{ $status }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>

                                    {{-- Hapus Insiden --}}
                                    <form
                                        action="{{ route('pusat-risiko.destroy', $item->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus insiden ini?')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-outline !text-[9px] !px-2 !py-1 w-full"
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"
                                class="td text-center !py-6 text-slate-400">
                                Belum ada insiden risiko.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Kesehatan Nomor WhatsApp --}}
    <div class="card !p-3">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="font-semibold text-xs">
                    Kesehatan Nomor WhatsApp
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    Pemantauan performa dan risiko setiap nomor WhatsApp.
                </div>
            </div>

            <span class="text-[10px] text-slate-500">
                {{ $kesehatanNomor->count() }} Nomor
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach ([
                            'Nama Nomor',
                            'Nomor WhatsApp',
                            'Status',
                            'Terkirim',
                            'Gagal',
                            'Ditunda',
                            'Ditahan',
                            'Skor Kesehatan',
                            'Kondisi'
                        ] as $h)
                            <th class="th !text-[9px] !px-2 !py-2 whitespace-nowrap">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($kesehatanNomor as $nomor)
                        <tr>
                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $nomor['nama'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $nomor['nomor'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                {{ $nomor['status'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2 text-green-600 font-medium">
                                {{ $nomor['terkirim'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2 text-red-600 font-medium">
                                {{ $nomor['gagal'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2 text-amber-600">
                                {{ $nomor['ditunda'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2 text-red-600">
                                {{ $nomor['ditahan'] }}
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                @if ($nomor['skor_kesehatan'] !== null)
                                    <div class="font-semibold">
                                        {{ $nomor['skor_kesehatan'] }}/100
                                    </div>

                                    <div class="w-20 h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden">
                                        <div
                                            class="h-full rounded-full
                                            {{ $nomor['skor_kesehatan'] >= 80
                                                ? 'bg-green-500'
                                                : ($nomor['skor_kesehatan'] >= 50
                                                    ? 'bg-amber-500'
                                                    : 'bg-red-500') }}"
                                            style="width: {{ $nomor['skor_kesehatan'] }}%"
                                        ></div>
                                    </div>
                                @else
                                    <span class="text-slate-400">
                                        Belum ada data
                                    </span>
                                @endif
                            </td>

                            <td class="td !text-[9px] !px-2 !py-2">
                                <span class="badge {{ $nomor['warna'] }} !text-[9px] !px-1.5 !py-0.5">
                                    {{ $nomor['kategori'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9"
                                class="td text-center !py-6 text-slate-400">
                                Belum ada nomor WhatsApp yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pop-up Tambah Insiden --}}
    <div
        x-cloak
        x-show="bukaModalInsiden"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @click.self="bukaModalInsiden = false"
        @keydown.escape.window="bukaModalInsiden = false"
    >
        <div
            x-show="bukaModalInsiden"
            x-transition
            class="w-full max-w-lg rounded-xl bg-white shadow-xl"
            @click.stop
        >
            {{-- Header Pop-up --}}
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <div class="font-semibold text-sm">
                        Tambah Insiden Risiko
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1">
                        Masukkan informasi insiden yang terjadi.
                    </div>
                </div>

                <button
                    type="button"
                    @click="bukaModalInsiden = false"
                    class="text-slate-400 hover:text-slate-700 text-lg"
                >
                    &times;
                </button>
            </div>

            {{-- Form Tambah Insiden --}}
            <form
                action="{{ route('pusat-risiko.store') }}"
                method="POST"
                class="p-4 space-y-3"
            >
                @csrf

                <div>
                    <label class="block text-[10px] font-medium mb-1">
                        Nomor WhatsApp
                    </label>

                    <select
                        name="nomor_whatsapp_id"
                        class="field w-full text-xs"
                    >
                        <option value="">
                            Tidak terkait nomor tertentu
                        </option>

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
                    <label class="block text-[10px] font-medium mb-1">
                        Jenis Insiden
                    </label>

                    <input
                        type="text"
                        name="jenis_insiden"
                        class="field w-full text-xs"
                        placeholder="Contoh: Risiko pemblokiran nomor"
                        maxlength="100"
                        value="{{ old('jenis_insiden') }}"
                        required
                    >
                </div>

                <div>
                    <label class="block text-[10px] font-medium mb-1">
                        Tingkat Risiko
                    </label>

                    <select
                        name="tingkat_risiko"
                        class="field w-full text-xs"
                        required
                    >
                        <option value="">
                            Pilih tingkat risiko
                        </option>

                        @foreach ([
                            'Rendah',
                            'Sedang',
                            'Tinggi',
                            'Kritis'
                        ] as $risiko)
                            <option
                                value="{{ $risiko }}"
                                @selected(old('tingkat_risiko') === $risiko)
                            >
                                {{ $risiko }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-medium mb-1">
                        Deskripsi Insiden
                    </label>

                    <textarea
                        name="deskripsi"
                        rows="4"
                        class="field w-full text-xs"
                        placeholder="Jelaskan insiden yang terjadi..."
                        required
                    >{{ old('deskripsi') }}</textarea>
                </div>

                {{-- Tombol --}}
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="bukaModalInsiden = false"
                        class="btn btn-outline !text-[10px] !px-3 !py-2"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary !text-[10px] !px-3 !py-2"
                    >
                        Simpan Insiden
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
