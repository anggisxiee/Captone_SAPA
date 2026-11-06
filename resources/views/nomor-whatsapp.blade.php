
@extends('layouts.app')
@section('title', 'Nomor WhatsApp')
@section('subtitle', 'Sesi tertaut, mode AI, dan batas pesan harian')

@section('content')
<div x-data="{
    tambah: false,
    edit: false,
    hapus: false,
    target: null,
    form: {
        nama: '',
        nomor: ''
    },
    bukaEdit(item) {
        this.target = item;
        this.form.nama = item.nama;
        this.form.nomor = item.nomor;
        this.edit = true;
    },
    bukaHapus(item) {
        this.target = item;
        this.hapus = true;
    }
}" class="text-xs">

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
                            {{ $item->nomor }}
                        </div>
                    </div>

                    <span class="badge {{ $item->status === 'Aktif' ? 'b-green' : 'b-red' }} self-start !text-[9px] !px-2 !py-0.5">
                        {{ $item->status === 'Aktif' ? 'Tersambung' : 'Nonaktif' }}
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
                    <span class="font-medium text-right">-</span>
                </div>

                <div class="flex gap-1.5 mt-3 flex-wrap">

                    {{-- Kelola --}}
                    <button
                        @click='bukaEdit(@json(["id" => $item->id, "nama" => $item->nama, "nomor" => $item->nomor]))'
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
                        @click='bukaHapus(@json(["id" => $item->id, "nama" => $item->nama]))'
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

            <form :action="'/nomor-whatsapp/' + target.id" method="POST">
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

            <form :action="'/nomor-whatsapp/' + target.id" method="POST">
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

</div>
@endsection
