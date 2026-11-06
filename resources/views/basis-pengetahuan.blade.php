
@extends('layouts.app')
@section('title', 'Basis Pengetahuan')
@section('subtitle', 'Artikel rujukan AI, dicari dengan BM25 (SQLite FTS5)')

@section('actions')
    <button class="btn btn-primary !text-[10px] !px-3 !py-1.5">Tambah artikel</button>
@endsection

@section('content')
@php
    $kategori = [
        ['Semua',42],
        ['Program & kurikulum',12],
        ['Jadwal',8],
        ['Biaya & cicilan',7],
        ['Fasilitas',5],
        ['Pendaftaran',6],
        ['Ujian & tryout',4]
    ];

    $artikel = [
        ['KB-012','Jadwal dan biaya SMP kelas 8','Jadwal','Aktif','b-green'],
        ['KB-004','Skema diskon kakak-beradik','Biaya & cicilan','Aktif','b-green'],
        ['KB-025','Prosedur pendaftaran tryout SNBT','Ujian & tryout','Aktif','b-green'],
        ['KB-008','Fasilitas kelas dan ruang belajar','Fasilitas','Aktif','b-green'],
        ['KB-019','Kebijakan garansi peningkatan nilai','Program & kurikulum','Draf','b-amber'],
    ];

    $hasil = [
        ['KB-012','Jadwal kelas 8 SMP','0,94','b-green'],
        ['KB-004','Skema diskon kakak-beradik','0,88','b-green'],
        ['KB-019','Kebijakan garansi','0,52','b-red']
    ];
@endphp

<div class="grid grid-cols-[minmax(0,1fr)_230px] gap-3 text-xs items-stretch">

    {{-- Bagian Kiri --}}
    <div class="grid grid-cols-[145px_minmax(0,1fr)] gap-3 items-stretch min-w-0">

        {{-- Kategori --}}
        <div class="card !p-3 h-full">
            <div class="font-semibold text-xs mb-2">Kategori</div>

            @foreach ($kategori as $i => [$nama, $jml])
                <div class="flex justify-between items-center gap-1 rounded-md px-2 py-1.5 text-[10px] cursor-pointer
                    {{ $i === 0 ? 'bg-teal-50 text-teal-700 font-medium' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>{{ $nama }}</span>
                    <span class="shrink-0">{{ $jml }}</span>
                </div>
            @endforeach
        </div>

        {{-- Daftar Artikel --}}
        <div class="min-w-0 flex flex-col">
            <div class="flex gap-2 mb-2">
                <input
                    class="field !text-[10px] !py-1.5 min-w-0 flex-1"
                    placeholder="Cari judul atau kata kunci">

                <select class="field w-24 !text-[10px] !py-1.5 shrink-0">
                    <option>Status</option>
                    <option>Aktif</option>
                    <option>Draf</option>
                </select>
            </div>

            <div class="card !p-0 overflow-hidden flex-1">
                <table class="w-full table-fixed border-collapse">
                    <colgroup>
                        <col style="width: 15%">
                        <col style="width: 38%">
                        <col style="width: 29%">
                        <col style="width: 18%">
                    </colgroup>

                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Kode</th>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Judul artikel</th>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Kategori</th>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($artikel as [$kode, $judul, $kat, $status, $warna])
                            <tr class="hover:bg-slate-50 cursor-pointer">
                                <td class="td !text-[9px] !px-1.5 !py-2 text-slate-500 break-words">
                                    {{ $kode }}
                                </td>

                                <td class="td !text-[10px] !px-1.5 !py-2 font-medium break-words">
                                    {{ $judul }}
                                </td>

                                <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                                    {{ $kat }}
                                </td>

                                <td class="td !text-[9px] !px-1.5 !py-2">
                                    <span class="badge {{ $warna }} !text-[9px] !px-1.5 !py-0.5">
                                        {{ $status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-[10px] text-slate-400 mt-2">
                Menampilkan 1–5 dari 42 artikel
            </div>
        </div>
    </div>

    {{-- Panel Uji Pencarian --}}
    <div class="card !p-3 h-full space-y-2.5">
        <div class="font-semibold text-xs">Uji pencarian</div>

        <div>
            <label class="label !text-[10px]">Pertanyaan uji</label>
            <textarea class="field !text-[10px] !py-2" rows="2">jadwal kelas 8 SMP dan diskon kakak beradik</textarea>
        </div>

        <button class="btn btn-primary w-full !text-[10px] !py-1.5">
            Jalankan uji
        </button>

        <div class="font-semibold text-[10px] pt-1">Hasil teratas</div>

        @foreach ($hasil as [$kode, $judul, $skor, $warna])
            <div class="flex justify-between items-center gap-2 text-[10px]">
                <span class="min-w-0 break-words">
                    <span class="font-medium">{{ $kode }}</span>
                    {{ $judul }}
                </span>

                <span class="badge {{ $warna }} !text-[9px] !px-1.5 !py-0.5 shrink-0">
                    {{ $skor }}
                </span>
            </div>
        @endforeach

        <p class="text-[9px] leading-relaxed text-slate-400 pt-1">
            Artikel dengan skor di bawah ambang 0,70 tidak dipakai AI.
        </p>
    </div>

</div>
@endsection
