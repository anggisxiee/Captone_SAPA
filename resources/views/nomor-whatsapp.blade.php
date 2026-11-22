
@extends('layouts.app')
@section('title', 'Nomor WhatsApp')
@section('subtitle', 'Sesi tertaut, mode AI, dan batas pesan harian')

@section('content')
@php
    // Setelah menambah nomor, Administrator langsung diarahkan ke QR.
    $nomorBaru = session('tautkan_id')
        ? $nomorWhatsapp->firstWhere('id', session('tautkan_id'))
        : null;
    $tautkanOtomatis = ($nomorBaru && $penggunaLogin->role === 'Administrator')
        ? ['id' => $nomorBaru->id, 'nama' => $nomorBaru->nama, 'nomor' => $nomorBaru->nomor]
        : null;
@endphp
<div x-data="{
    tambah: false,
    edit: false,
    hapus: false,
    target: null,
    form: {
        nama: '',
        nomor: '',
        tujuan: 'Admin'
    },
    qr: { buka: false, id: null, nama: '', nomor: '', svg: null, sisa: 0, kedaluwarsa: false, galat: '' },
    pewaktu: null,
    csrf: @js(csrf_token()),
    urlNomor(id, aksi) {
        return '{{ url('/nomor-whatsapp') }}/' + id + '/' + aksi;
    },
    async panggil(url, metode) {
        const res = await fetch(url, {
            method: metode,
            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) { this.qr.galat = data.pesan || 'Terjadi kesalahan.'; return null; }
        this.qr.galat = '';
        return data;
    },
    terapkan(data) {
        if (!data) return;
        this.qr.svg = data.qr_svg;
        this.qr.sisa = data.sisa_detik;
        this.qr.kedaluwarsa = data.kedaluwarsa;
        if (data.status_sesi === 'Tersambung') {
            this.tutupQr();
            window.location.reload();
        }
    },
    async mulaiTautan(item) {
        this.qr = { buka: true, id: item.id, nama: item.nama, nomor: item.nomor, svg: null, sisa: 0, kedaluwarsa: false, galat: '' };
        this.terapkan(await this.panggil(this.urlNomor(item.id, 'tautkan'), 'POST'));
        this.mulaiPantau();
    },
    mulaiPantau() {
        clearInterval(this.pewaktu);
        let tik = 0;
        this.pewaktu = setInterval(async () => {
            if (this.qr.sisa > 0) this.qr.sisa--;
            if (this.qr.sisa === 0 && this.qr.svg) this.qr.kedaluwarsa = true;
            // Sinkron ke server setiap 2 detik.
            if (++tik % 2 === 0 && this.qr.id) {
                this.terapkan(await this.panggil(this.urlNomor(this.qr.id, 'sesi'), 'GET'));
            }
        }, 1000);
    },
    tutupQr() {
        clearInterval(this.pewaktu);
        this.qr.buka = false;
    },
    async simulasiPindai() {
        this.terapkan(await this.panggil(this.urlNomor(this.qr.id, 'simulasi-pindai'), 'POST'));
    },
    bukaEdit(item) {
        this.target = item;
        this.form.nama = item.nama;
        this.form.nomor = item.nomor;
        this.form.tujuan = item.tujuan;
        this.edit = true;
    },
    bukaHapus(item) {
        this.target = item;
        this.hapus = true;
    }
}"
@if ($tautkanOtomatis)
    x-init="mulaiTautan(@js($tautkanOtomatis))"
@endif
class="text-xs">

    {{-- Tombol tambah --}}
    <div class="flex justify-end -mt-12 mb-4">
        <button @click="tambah = true"
            class="btn btn-primary !text-[11px] !px-3 !py-1.5">
            Tautkan nomor
        </button>
    </div>

    {{-- Pesan berhasil --}}
    @if (session('success'))
        <div class="mb-3 rounded-lg bg-green-50 border border-green-200 px-3 py-2 text-[11px] text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Pesan validasi --}}
    @if ($errors->any())
        <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="space-y-3">

        {{-- Daftar nomor dari database --}}
        @forelse ($nomorWhatsapp as $item)
            <div class="card !p-3">

                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-semibold text-xs">
                            {{ $item->nama }}
                        </div>
                        <div class="text-[10px] text-slate-400 mt-0.5">
                            {{ $item->nomor }} · {{ $item->tujuan }}
                        </div>
                    </div>

                    @php
                        $labelSesi = $item->status !== 'Aktif'
                            ? 'Nonaktif'
                            : ($item->qrKedaluwarsa() ? 'QR kedaluwarsa' : $item->status_sesi);
                        $warnaSesi = match ($labelSesi) {
                            'Tersambung' => 'b-green',
                            'Menunggu QR', 'QR kedaluwarsa' => 'b-amber',
                            'Belum tertaut', 'Nonaktif' => 'b-slate',
                            default => 'b-red',
                        };
                    @endphp
                    <span class="badge {{ $warnaSesi }} self-start !text-[9px] !px-2 !py-0.5">
                        {{ $labelSesi }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-x-8 gap-y-1.5 mt-3 text-[10px] w-full">
                    <span class="text-slate-500">Mode AI</span>
                    <span class="font-medium text-right">Belum diatur</span>

                    <span class="text-slate-500">Kesehatan nomor</span>
                    <span class="font-medium text-right">-</span>

                    <span class="text-slate-500">Pesan keluar hari ini</span>
                    <span class="font-medium text-right">-</span>

                    <span class="text-slate-500">Sesi terakhir aktif</span>
                    <span class="font-medium text-right">{{ $item->terakhir_aktif_pada?->diffForHumans() ?? '-' }}</span>
                </div>

                <div class="flex gap-1.5 mt-3 flex-wrap">

                    @if ($penggunaLogin->role === 'Administrator' && $item->status === 'Aktif')
                        @if ($item->status_sesi === 'Tersambung')
                            <form action="{{ route('nomor-whatsapp.putuskan', $item->id) }}" method="POST"
                                onsubmit="return confirm('Putuskan sesi WhatsApp nomor ini?')">
                                @csrf
                                <button type="submit" class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                                    Putuskan
                                </button>
                            </form>
                        @else
                            <button
                                @click="mulaiTautan(@js(['id' => $item->id, 'nama' => $item->nama, 'nomor' => $item->nomor]))"
                                class="btn btn-primary !text-[10px] !px-2.5 !py-1">
                                {{ $item->status_sesi === 'Belum tertaut' ? 'Tautkan' : 'Tautkan ulang' }}
                            </button>
                        @endif
                    @endif

                    {{-- Kelola --}}
                    <button
                        @click="bukaEdit(@js(['id' => $item->id, 'nama' => $item->nama, 'nomor' => $item->nomor, 'tujuan' => $item->tujuan]))"
                        class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                        Kelola
                    </button>

                    {{-- Ubah status --}}
                    <form action="{{ route('nomor-whatsapp.status', $item->id) }}"
                        method="POST">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                            class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                            {{ $item->status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>

                    {{-- Hapus --}}
                    <button
                        @click="bukaHapus(@js(['id' => $item->id, 'nama' => $item->nama]))"
                        class="btn btn-danger !text-[10px] !px-2.5 !py-1">
                        Hapus
                    </button>

                </div>
            </div>
        @empty
            <div class="card !p-5 text-center">
                <div class="text-xs font-semibold text-slate-600">
                    Belum ada nomor WhatsApp
                </div>
                <p class="text-[10px] text-slate-400 mt-1">
                    Klik tombol Tautkan nomor untuk menambahkan nomor WhatsApp.
                </p>
            </div>
        @endforelse

        {{-- Kebijakan anti-ban --}}
        <div class="card bg-slate-50 !p-3">
            <div class="font-semibold text-xs mb-1">
                Kebijakan anti-ban
            </div>
            <p class="text-[10px] leading-relaxed text-slate-500">
                Pesan keluar dibatasi per nomor dengan jeda acak 3–8 detik dan batas harian.
                Jika kesehatan nomor turun di bawah 70, kecepatan kirim dikurangi otomatis.
                Pengaturan lengkap ada di menu Pengaturan AI dan Antrean &amp; Risiko.
            </p>
        </div>
    </div>

    {{-- Modal tambah nomor --}}
    <div x-show="tambah" x-cloak
        class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">

        <div @click.outside="tambah = false"
            class="bg-white rounded-xl p-4 w-full max-w-sm">

            <h3 class="font-semibold text-sm mb-3">
                Tambah nomor WhatsApp
            </h3>

            <form action="{{ route('nomor-whatsapp.store') }}" method="POST">
                @csrf

                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1">
                            Nama nomor
                        </label>
                        <input type="text" name="nama" required
                            placeholder="Contoh: Admin Asentra"
                            class="field w-full !text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1">
                            Nomor WhatsApp
                        </label>
                        <input type="text" name="nomor" required
                            placeholder="Contoh: 081234567890"
                            class="field w-full !text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1">
                            Tujuan nomor
                        </label>
                        <select name="tujuan" class="field w-full !text-xs">
                            <option value="Admin">Admin</option>
                            <option value="Sales">Sales</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="tambah = false"
                        class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                        Batal
                    </button>
                    <button type="submit"
                        class="btn btn-primary !text-[10px] !px-2.5 !py-1">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal edit nomor --}}
    <div x-show="edit" x-cloak
        class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">

        <div @click.outside="edit = false"
            class="bg-white rounded-xl p-4 w-full max-w-sm">

            <h3 class="font-semibold text-sm mb-3">
                Kelola nomor WhatsApp
            </h3>

            <form :action="'/nomor-whatsapp/' + target?.id" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1">
                            Nama nomor
                        </label>
                        <input type="text" name="nama" x-model="form.nama"
                            required class="field w-full !text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1">
                            Nomor WhatsApp
                        </label>
                        <input type="text" name="nomor" x-model="form.nomor"
                            required class="field w-full !text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1">
                            Tujuan nomor
                        </label>
                        <select name="tujuan" x-model="form.tujuan" class="field w-full !text-xs">
                            <option value="Admin">Admin</option>
                            <option value="Sales">Sales</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="edit = false"
                        class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                        Batal
                    </button>
                    <button type="submit"
                        class="btn btn-primary !text-[10px] !px-2.5 !py-1">
                        Simpan perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal konfirmasi hapus --}}
    <div x-show="hapus" x-cloak
        class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">

        <div @click.outside="hapus = false"
            class="bg-white rounded-xl p-4 w-full max-w-sm">

            <h3 class="font-semibold text-sm">
                Hapus nomor WhatsApp?
            </h3>

            <p class="text-[11px] text-slate-500 mt-2">
                Nomor <span class="font-semibold text-slate-700" x-text="target?.nama"></span>
                akan dihapus dari daftar.
            </p>

            <form :action="'/nomor-whatsapp/' + target?.id" method="POST">
                @csrf
                @method('DELETE')

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="hapus = false"
                        class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                        Batal
                    </button>
                    <button type="submit"
                        class="btn btn-danger !text-[10px] !px-2.5 !py-1">
                        Ya, hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($penggunaLogin->role === 'Administrator')
    {{-- Modal penautan QR --}}
    <div x-show="qr.buka" x-cloak
        class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">

        <div class="bg-white rounded-xl p-5 w-full max-w-xl">

            <h3 class="font-semibold text-sm">Tautkan nomor WhatsApp</h3>
            <p class="text-[10px] text-slate-500 mt-0.5">
                Nomor target: <span x-text="qr.nama"></span> (<span x-text="qr.nomor"></span>)
            </p>

            <div class="grid grid-cols-[1fr_200px] gap-5 mt-4">

                <ol class="space-y-2.5 text-[11px] text-slate-700">
                    <li><span class="text-teal-700 font-semibold">1.</span> Buka WhatsApp di ponsel nomor tersebut.</li>
                    <li><span class="text-teal-700 font-semibold">2.</span> Ketuk Menu atau Pengaturan, lalu pilih Perangkat tertaut.</li>
                    <li><span class="text-teal-700 font-semibold">3.</span> Ketuk Tautkan perangkat.</li>
                    <li><span class="text-teal-700 font-semibold">4.</span> Arahkan kamera ke kode QR di samping.</li>
                </ol>

                <div>
                    <div class="w-[200px] h-[200px] bg-slate-50 border border-slate-200 relative">
                        <div x-html="qr.svg" :class="qr.kedaluwarsa ? 'opacity-20' : ''"></div>
                        <div x-show="!qr.svg" class="absolute inset-0 flex items-center justify-center text-[10px] text-slate-400">
                            Membuat kode…
                        </div>
                        <div x-show="qr.kedaluwarsa" class="absolute inset-0 flex items-center justify-center text-[11px] font-semibold text-amber-700">
                            QR kedaluwarsa
                        </div>
                    </div>
                    <p class="text-[10px] text-center mt-1.5 text-amber-700" x-show="qr.svg && !qr.kedaluwarsa">
                        Kode kedaluwarsa dalam <span x-text="qr.sisa"></span> detik
                    </p>
                </div>
            </div>

            <p class="text-[10px] text-red-600 mt-2" x-show="qr.galat" x-text="qr.galat"></p>

            <div class="flex justify-between items-center mt-4">
                <button type="button" @click="mulaiTautan({ id: qr.id, nama: qr.nama, nomor: qr.nomor })"
                    class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                    Buat ulang kode
                </button>

                <div class="flex gap-2">
                    @if (config('whatsapp.driver') === 'fake')
                        <button type="button" @click="simulasiPindai()" x-show="qr.svg && !qr.kedaluwarsa"
                            class="btn btn-outline !text-[10px] !px-2.5 !py-1 border-dashed">
                            Simulasikan pindai (mode uji)
                        </button>
                    @endif
                    <button type="button" @click="tutupQr()"
                        class="btn btn-outline !text-[10px] !px-2.5 !py-1">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
