
@extends('layouts.app')
@section('title', 'Kontak')
@section('subtitle', '142 calon siswa dan wali murid')

@section('actions')
    <select class="field w-auto !text-[10px] !py-1.5">
        <option>Semua nomor</option>
    </select>
    <button class="btn btn-outline !text-[10px] !py-1.5">
        Ekspor CSV
    </button>
@endsection

@section('content')
@php
    $kontak = [
        ['nama'=>'Rina Kusuma','nomor'=>'0812-3456-7890','jenjang'=>'SMP','kelas'=>'Kelas 8','mapel'=>'Matematika','cabang'=>'Bandung (Dago)','sumber'=>'Iklan Instagram','tahap'=>'Tertarik','terakhir'=>'Hari ini 10:14'],
        ['nama'=>'Hendra Setiawan','nomor'=>'0812-1111-2222','jenjang'=>'SMA','kelas'=>'Kelas 12','mapel'=>'UTBK SNBT','cabang'=>'Sragen','sumber'=>'Iklan Instagram','tahap'=>'Baru','terakhir'=>'Hari ini 09:45'],
        ['nama'=>'Sinta Nurhaliza','nomor'=>'0812-4444-5555','jenjang'=>'SMP','kelas'=>'Kelas 9','mapel'=>'Ujian sekolah','cabang'=>'Sragen','sumber'=>'Rekomendasi','tahap'=>'Dihubungi','terakhir'=>'Hari ini 09:12'],
        ['nama'=>'Dimas Aditya','nomor'=>'0857-6666-7777','jenjang'=>'SMA','kelas'=>'Kelas 11','mapel'=>'Fisika & Kimia','cabang'=>'Sragen','sumber'=>'Situs web','tahap'=>'Dihubungi','terakhir'=>'Kemarin'],
        ['nama'=>'Kevin Sanjaya','nomor'=>'0813-2222-3333','jenjang'=>'SD','kelas'=>'Kelas 6','mapel'=>'Persiapan asesmen','cabang'=>'Sragen','sumber'=>'Rekomendasi','tahap'=>'Baru','terakhir'=>'Kemarin'],
        ['nama'=>'Nadya Amalia','nomor'=>'0852-1212-3434','jenjang'=>'Alumni','kelas'=>'-','mapel'=>'Kelas persiapan','cabang'=>'Sragen','sumber'=>'Situs web','tahap'=>'Penawaran','terakhir'=>'22 Okt'],
    ];
@endphp

<div x-data="{ open: false, c: {} }">

    {{-- Pencarian --}}
    <div class="flex items-center gap-2 mb-3">
        <input
            class="field max-w-xs !text-[10px] !py-1.5"
            placeholder="Cari nama atau nomor"
        >
    </div>

    {{-- Tabel kontak --}}
    <div class="card !p-0 overflow-hidden">
        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col style="width: 15%">
                <col style="width: 14%">
                <col style="width: 11%">
                <col style="width: 13%">
                <col style="width: 13%">
                <col style="width: 9%">
                <col style="width: 15%">
                <col style="width: 10%">
            </colgroup>

            <thead class="bg-slate-50">
                <tr>
                    @foreach (['Nama','Nomor WhatsApp','Jenjang','Mata pelajaran','Sumber','Tahap','Terakhir dihubungi','Aksi'] as $h)
                        <th class="th !text-[9px] !px-1.5 !py-2 text-left">
                            {{ $h }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($kontak as $k)
                    <tr>
                        <td class="td !text-[10px] !px-1.5 !py-2 font-medium break-words">
                            {{ $k['nama'] }}
                        </td>

                        <td class="td !text-[9px] !px-1.5 !py-2 break-all">
                            {{ $k['nomor'] }}
                        </td>

                        <td class="td !text-[9px] !px-1.5 !py-2">
                            {{ $k['jenjang'] }} {{ $k['kelas'] }}
                        </td>

                        <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                            {{ $k['mapel'] }}
                        </td>

                        <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                            {{ $k['sumber'] }}
                        </td>

                        <td class="td !px-1.5 !py-2">
                            <span class="badge b-slate !text-[8px] !px-1.5 !py-0.5">
                                {{ $k['tahap'] }}
                            </span>
                        </td>

                        <td class="td !text-[9px] !px-1.5 !py-2 break-words">
                            {{ $k['terakhir'] }}
                        </td>

                        <td class="td !px-1.5 !py-2">
                            <button
                                @click='c = @json($k); open = true'
                                class="btn btn-outline !text-[9px] !px-2 !py-1"
                            >
                                Ubah
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="text-[10px] text-slate-400 mt-2">
        Menampilkan 1–6 dari 142 kontak
    </div>

    {{-- Modal ubah kontak --}}
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4"
        @keydown.escape.window="open=false"
    >
        <div
            @click.outside="open=false"
            class="bg-white rounded-xl p-4 w-full max-w-md space-y-3 shadow-xl"
        >
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Ubah kontak</h3>

                <button
                    @click="open=false"
                    class="text-slate-400 text-sm"
                >
                    ✕
                </button>
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="label !text-[10px]">Nama lengkap</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.nama">
                </div>

                <div>
                    <label class="label !text-[10px]">Nomor WhatsApp</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.nomor">
                    <p class="text-[9px] text-slate-400 mt-1">
                        Nomor tidak boleh sama dengan kontak lain.
                    </p>
                </div>

                <div>
                    <label class="label !text-[10px]">Jenjang</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.jenjang">
                </div>

                <div>
                    <label class="label !text-[10px]">Kelas</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.kelas">
                </div>

                <div class="col-span-2">
                    <label class="label !text-[10px]">Mata pelajaran peminatan</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.mapel">
                </div>

                <div>
                    <label class="label !text-[10px]">Cabang</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.cabang">
                </div>

                <div>
                    <label class="label !text-[10px]">Sumber</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.sumber">
                </div>

                <div class="col-span-2">
                    <label class="label !text-[10px]">Tahap prospek</label>
                    <input class="field !text-[11px] !py-1.5" x-model="c.tahap">
                </div>
            </div>

            <div class="flex justify-between items-center pt-2">
                <button class="btn btn-danger !text-[10px] !py-1.5 !px-2.5">
                    Hapus kontak
                </button>

                <div class="flex gap-2">
                    <button
                        @click="open=false"
                        class="btn btn-outline !text-[10px] !py-1.5 !px-2.5"
                    >
                        Batal
                    </button>

                    <button
                        @click="open=false"
                        class="btn btn-primary !text-[10px] !py-1.5 !px-2.5"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
