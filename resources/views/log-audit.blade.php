@extends('layouts.app')
@section('title', 'Log Audit')
@section('subtitle', 'Jejak perubahan oleh pengguna, AI, dan sistem')

@section('content')
@php
    $log = [
        ['id'=>8842,'waktu'=>'10:24:15','pelaku'=>'Dewi Anggraini','aksi'=>'Ubah akses nomor','ringkas'=>'Hak akses Raka Pratama pada nomor 1002: Lihat saja → Balas chat','modul'=>'Tim & Akses','target'=>'Raka Pratama','sebelum'=>'Lihat saja','sesudah'=>'Balas chat','alasan'=>'Raka mendampingi penerimaan siswa baru SMP cabang Dago dan perlu membalas langsung.'],
        ['id'=>8841,'waktu'=>'10:18:02','pelaku'=>'Yordan','aksi'=>'Ubah pengaturan AI','ringkas'=>'Ambang keyakinan 0,58 → 0,62','modul'=>'Pengaturan AI','target'=>'Ambang keyakinan','sebelum'=>'0,58','sesudah'=>'0,62','alasan'=>'Mengurangi jawaban AI yang kurang yakin.'],
        ['id'=>8840,'waktu'=>'09:45:10','pelaku'=>'Raka Pratama','aksi'=>'Ambil alih chat','ringkas'=>'Mengambil alih percakapan Bunda Arina dari AI','modul'=>'Kotak Masuk','target'=>'Bunda Arina','sebelum'=>'AI','sesudah'=>'Manusia','alasan'=>'Pelanggan meminta bicara dengan admin.'],
        ['id'=>8839,'waktu'=>'09:12:33','pelaku'=>'SAPA AI','aksi'=>'Eskalasi otomatis','ringkas'=>'Skor pencarian 0,40 di bawah ambang; dialihkan ke agen','modul'=>'Kotak Masuk','target'=>'Sinta (Ibu Dita)','sebelum'=>'AI','sesudah'=>'Agen','alasan'=>'Keyakinan AI rendah.'],
        ['id'=>8838,'waktu'=>'08:30:19','pelaku'=>'Yordan','aksi'=>'Tautkan nomor','ringkas'=>'Menautkan ulang sesi nomor 1002 lewat kode QR','modul'=>'Nomor WhatsApp','target'=>'Sales Asentra','sebelum'=>'Terputus','sesudah'=>'Tersambung','alasan'=>'Sesi kedaluwarsa.'],
        ['id'=>8837,'waktu'=>'08:00:04','pelaku'=>'Dewi Anggraini','aksi'=>'Login','ringkas'=>'Masuk berhasil','modul'=>'Autentikasi','target'=>'-','sebelum'=>'-','sesudah'=>'-','alasan'=>'-'],
    ];
@endphp

<div x-data='{ sel: @json($log[0]) }' class="flex gap-4">
    <div class="flex-1 min-w-0">
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="card"><div class="text-xs text-slate-500">Log hari ini</div><div class="text-2xl font-semibold">148</div><div class="text-xs text-slate-400">+18% dari kemarin</div></div>
            <div class="card"><div class="text-xs text-slate-500">Aksi pengguna</div><div class="text-2xl font-semibold">82</div><div class="text-xs text-slate-400">Perubahan manual</div></div>
        </div>
        <div class="flex gap-2 mb-3">
            <input class="field" placeholder="Cari target atau ringkasan">
            <select class="field w-auto"><option>Pelaku</option></select>
            <select class="field w-auto"><option>Jenis aksi</option></select>
            <select class="field w-auto"><option>Hari ini</option><option>7 hari</option><option>30 hari</option></select>
        </div>
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50"><tr><th class="th">Waktu</th><th class="th">Pelaku</th><th class="th">Aksi</th><th class="th">Ringkasan perubahan</th></tr></thead>
                <tbody>
                @foreach ($log as $l)
                    <tr @click='sel = @json($l)' class="cursor-pointer hover:bg-slate-50">
                        <td class="td">{{ $l['waktu'] }}</td><td class="td">{{ $l['pelaku'] }}</td><td class="td">{{ $l['aksi'] }}</td><td class="td text-slate-600">{{ $l['ringkas'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-xs text-slate-400 mt-3">Menampilkan 1–6 dari 148 catatan · Log tidak dapat diubah atau dihapus.</div>
    </div>

    <aside class="w-72 shrink-0 card self-start space-y-3">
        <div class="flex justify-between"><span class="font-semibold">Detail log #<span x-text="sel.id"></span></span></div>
        <div class="text-xs space-y-1">
            <div class="flex justify-between"><span class="text-slate-500">Waktu</span><span x-text="sel.waktu + ' WIB'"></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Pelaku</span><span x-text="sel.pelaku"></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Modul</span><span x-text="sel.modul"></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Target</span><span x-text="sel.target"></span></div>
        </div>
        <div class="font-semibold text-xs">Perubahan</div>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-lg bg-red-50 text-red-600 p-2"><div class="text-[11px]">Sebelum</div><b x-text="sel.sebelum"></b></div>
            <div class="rounded-lg bg-emerald-50 text-emerald-700 p-2"><div class="text-[11px]">Sesudah</div><b x-text="sel.sesudah"></b></div>
        </div>
        <div class="font-semibold text-xs">Alasan perubahan</div>
        <p class="text-xs text-slate-600" x-text="sel.alasan"></p>
    </aside>
</div>
@endsection