
@extends('layouts.app')
@section('title', 'Dasbor')
@section('subtitle', 'Ringkasan percakapan, balasan AI, dan prospek')

@section('actions')
    <select class="field w-auto !text-[11px] !py-1.5">
        <option>Semua nomor</option>
        <option>Admin Asentra</option>
        <option>Sales Asentra</option>
    </select>
    <select class="field w-auto !text-[11px] !py-1.5">
        <option>30 hari</option>
        <option>7 hari</option>
        <option>Hari ini</option>
    </select>
@endsection

@section('content')
@php
    $stats = [
        ['Percakapan masuk', '128', '+12% dari periode lalu'],
        ['Rata-rata waktu balas pertama', '1 mnt 42 dtk', 'Turun 18%'],
        ['Belum dibalas', '3', 'Perlu tindak lanjut'],
        ['Prospek baru', '9', 'Konversi mendaftar 7%'],
    ];

    $hari = [
        'Sen' => 45, 'Sel' => 70, 'Rab' => 55,
        'Kam' => 100, 'Jum' => 75, 'Sab' => 30, 'Min' => 20
    ];

    $nomor = [
        ['Admin Asentra', '0812-xxxx-1001', 84, 142, 2, '1 mnt 15 dtk'],
        ['Sales Asentra', '0812-xxxx-1002', 44, 86, 1, '2 mnt 24 dtk'],
    ];

    $prospek = [
        ['Rina Kusuma', 'Paket SNBT Intensif', 78],
        ['Bapak Hendra', 'Jadwal dan biaya', 74],
        ['Sinta', 'Program asesmen 2026', 68],
        ['Agus Wibowo', 'Promo cicilan', 62]
    ];
@endphp

{{-- Statistik --}}
<div class="grid grid-cols-4 gap-3 mb-3">
    @foreach ($stats as [$label, $nilai, $ket])
        <div class="card !p-3">
            <div class="text-[10px] text-slate-500">{{ $label }}</div>
            <div class="text-lg font-semibold mt-1">{{ $nilai }}</div>
            <div class="text-[10px] text-slate-400 mt-1">{{ $ket }}</div>
        </div>
    @endforeach
</div>

{{-- Grafik --}}
<div class="grid grid-cols-3 gap-3 mb-3">
    <div class="card col-span-2 !p-3">
        <div class="text-xs font-semibold mb-2">Percakapan per hari</div>

        <div class="flex items-end gap-3 h-32">
            @foreach ($hari as $h => $v)
                <div class="flex-1 flex flex-col items-center justify-end h-full gap-1">
                    <div
                        class="w-full rounded {{ $h === 'Kam' ? 'bg-teal-700' : 'bg-slate-200' }}"
                        style="height: {{ $v }}%">
                    </div>
                    <span class="text-[10px] text-slate-400">{{ $h }}</span>
                </div>
            @endforeach
        </div>

        <div class="text-[10px] text-slate-400 mt-2">
            Puncak: Kamis, 42 pesan, pukul 13.00–15.30 WIB
        </div>
    </div>

    <div class="card !p-3">
        <div class="text-xs font-semibold mb-3">Dibalas AI dan agen</div>

        <div class="flex gap-5">
            <div>
                <div class="text-2xl font-semibold text-teal-700">74%</div>
                <div class="text-[10px] text-slate-500">Dibalas AI</div>
            </div>

            <div>
                <div class="text-2xl font-semibold">26%</div>
                <div class="text-[10px] text-slate-500">Diambil agen</div>
            </div>
        </div>

        <p class="text-[10px] text-slate-500 mt-3 leading-relaxed">
            95 percakapan dijawab AI, 33 dieskalasi ke agen.
        </p>
    </div>
</div>

{{-- Tabel dan Prospek --}}
<div class="grid grid-cols-3 gap-3">
    <div class="card col-span-2 !p-3">
        <div class="text-xs font-semibold mb-2">
            Kinerja per nomor WhatsApp
        </div>

        <table class="w-full">
            <thead>
                <tr>
                    <th class="th !text-[10px] !py-2">Nomor</th>
                    <th class="th !text-[10px] !py-2">Status</th>
                    <th class="th !text-[10px] !py-2">Masuk</th>
                    <th class="th !text-[10px] !py-2">Keluar</th>
                    <th class="th !text-[10px] !py-2">Pending</th>
                    <th class="th !text-[10px] !py-2">Rata-rata balas</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($nomor as [$nama, $no, $masuk, $keluar, $pending, $balas])
                    <tr>
                        <td class="td !text-[11px] !py-2">
                            <div class="font-medium">{{ $nama }}</div>
                            <div class="text-[10px] text-slate-400">{{ $no }}</div>
                        </td>

                        <td class="td !py-2">
                            <span class="badge b-green !text-[9px] !px-2 !py-0.5">
                                Tersambung
                            </span>
                        </td>

                        <td class="td !text-[11px] !py-2">{{ $masuk }}</td>
                        <td class="td !text-[11px] !py-2">{{ $keluar }}</td>
                        <td class="td !text-[11px] !py-2">{{ $pending }}</td>
                        <td class="td !text-[11px] !py-2">{{ $balas }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card !p-3">
        <div class="text-xs font-semibold mb-2">Prospek prioritas</div>

        @foreach ($prospek as [$nama, $ket, $skor])
            <div class="flex items-center justify-between py-2 border-t border-slate-100 first:border-0 gap-2">
                <div class="min-w-0">
                    <div class="text-[11px] font-medium">{{ $nama }}</div>
                    <div class="text-[10px] text-slate-400">{{ $ket }}</div>
                </div>

                <span class="badge {{ $skor >= 70 ? 'b-green' : 'b-amber' }} !text-[9px] !px-2 !py-0.5 whitespace-nowrap">
                    Skor {{ $skor }}
                </span>
            </div>
        @endforeach
    </div>
</div>
@endsection
