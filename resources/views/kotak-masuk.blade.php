
@extends('layouts.app')
@section('title', 'Kotak Masuk')
@section('subtitle', 'Percakapan WhatsApp dari semua nomor')

@section('actions')
    <select
        class="field w-auto text-xs py-1.5"
        @change="$dispatch('nomor-filter', $event.target.value)"
    >
        <option value="">Semua nomor yang dapat diakses</option>

        @foreach ($nomorWhatsapp as $nomor)
            <option value="{{ $nomor->id }}">
                {{ $nomor->nama }}
            </option>
        @endforeach
    </select>

    <button class="btn btn-primary text-xs py-1.5">
        Pesan baru
    </button>
@endsection

@section('content')
@php
    $daftar = [
        [
            'id' => 1,
            'nama' => 'Rina Kusuma',
            'telepon' => '0812-3456-7890',
            'jam' => '10:14',
            'cuplikan' => 'Kalau untuk garansi nilai dan diskon kakak...',
            'nomor' => 'Admin 1001',
            'nomorId' => 2,
            'status' => 'Eskalasi AI',
            'warna' => 'b-amber',
            'jenjang' => 'SMP kelas 8',
            'mapel' => 'Matematika',
            'cabang' => 'Bandung (Dago)',
            'sumber' => 'Iklan Instagram',
            'tahap' => 'Tertarik',
            'skor' => '78 / 100',
            'maksud' => 'tanya_jadwal_dan_biaya',
            'keyakinan' => '0,41',
            'ambang' => '0,62',
            'artikel' => 'KB-012, KB-004',
            'pesan' => [
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Halo admin, mau tanya biaya dan jadwal bimbel persiapan ujian untuk kelas 8 SMP matematika di cabang Dago.',
                    'jam' => '10:10',
                ],
                [
                    'pengirim' => 'ai',
                    'isi' => 'Halo Kak Rina! Untuk kelas 8 SMP program Reguler Matematika cabang Dago tersedia Selasa & Kamis (16.00–17.30 WIB) atau Sabtu intensif. Biaya mulai Rp 450.000/bulan. Apakah ingin kami jadwalkan trial gratis minggu ini?',
                    'jam' => '10:11',
                ],
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Kalau untuk garansi nilai dan diskon kakak beradik bagaimana syaratnya?',
                    'jam' => '10:13',
                ],
                [
                    'pengirim' => 'sistem',
                    'isi' => 'Keyakinan AI 0,41 di bawah ambang 0,62. Percakapan dieskalasi ke agen dan AI berhenti membalas.',
                    'jam' => '',
                ],
            ],
        ],
        [
            'id' => 2,
            'nama' => 'Bapak Hendra',
            'telepon' => '0813-4567-8901',
            'jam' => '09:45',
            'cuplikan' => 'Baik min, besok sore saya transfer untuk...',
            'nomor' => 'Sales 1002',
            'nomorId' => 3,
            'status' => 'Dibalas AI',
            'warna' => 'b-green',
            'jenjang' => 'SMA kelas 11',
            'mapel' => 'Fisika',
            'cabang' => 'Yogyakarta',
            'sumber' => 'WhatsApp',
            'tahap' => 'Negosiasi',
            'skor' => '85 / 100',
            'maksud' => 'konfirmasi_pembayaran',
            'keyakinan' => '0,91',
            'ambang' => '0,62',
            'artikel' => 'KB-008, KB-010',
            'pesan' => [
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Selamat pagi admin, saya ingin mendaftar bimbel Fisika untuk anak saya kelas 11.',
                    'jam' => '09:40',
                ],
                [
                    'pengirim' => 'ai',
                    'isi' => 'Baik Pak Hendra. Untuk kelas 11 tersedia program reguler dan intensif. Apakah Bapak ingin mendapatkan informasi biaya terlebih dahulu?',
                    'jam' => '09:42',
                ],
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Baik min, besok sore saya transfer untuk pendaftarannya.',
                    'jam' => '09:45',
                ],
            ],
        ],
        [
            'id' => 3,
            'nama' => 'Sinta (Ibu Dita)',
            'telepon' => '0815-2345-6789',
            'jam' => '09:12',
            'cuplikan' => 'Apakah bisa request tentor perempuan?',
            'nomor' => 'Admin 1001',
            'nomorId' => 2,
            'status' => 'Terjawab',
            'warna' => 'b-slate',
            'jenjang' => 'SMP kelas 9',
            'mapel' => 'Bahasa Inggris',
            'cabang' => 'Bandung',
            'sumber' => 'Website',
            'tahap' => 'Konsultasi',
            'skor' => '65 / 100',
            'maksud' => 'permintaan_tentor',
            'keyakinan' => '0,88',
            'ambang' => '0,62',
            'artikel' => 'KB-005',
            'pesan' => [
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Apakah bisa request tentor perempuan untuk anak saya?',
                    'jam' => '09:10',
                ],
                [
                    'pengirim' => 'ai',
                    'isi' => 'Tentu, Ibu. Kami akan bantu mencarikan tentor perempuan yang sesuai dengan kebutuhan belajar anak.',
                    'jam' => '09:12',
                ],
            ],
        ],
        [
            'id' => 4,
            'nama' => 'Agus Wibowo',
            'telepon' => '0816-3456-7890',
            'jam' => 'Kemarin',
            'cuplikan' => 'Terima kasih informasinya, brosur sudah...',
            'nomor' => 'Sales 1002',
            'nomorId' => 3,
            'status' => 'Selesai',
            'warna' => 'b-slate',
            'jenjang' => 'SMA kelas 12',
            'mapel' => 'Matematika',
            'cabang' => 'Yogyakarta',
            'sumber' => 'WhatsApp',
            'tahap' => 'Selesai',
            'skor' => '50 / 100',
            'maksud' => 'permintaan_brosur',
            'keyakinan' => '0,95',
            'ambang' => '0,62',
            'artikel' => 'KB-002',
            'pesan' => [
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Boleh minta brosur program bimbel untuk kelas 12?',
                    'jam' => 'Kemarin',
                ],
                [
                    'pengirim' => 'ai',
                    'isi' => 'Tentu Pak Agus. Berikut informasi program bimbel dan brosur yang dapat dipelajari.',
                    'jam' => 'Kemarin',
                ],
                [
                    'pengirim' => 'pelanggan',
                    'isi' => 'Terima kasih informasinya, brosur sudah saya terima.',
                    'jam' => 'Kemarin',
                ],
            ],
        ],
    ];

    // Filter nomor WhatsApp berdasarkan hak akses pengguna
    $nomorDiizinkan = $nomorWhatsapp
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $daftar = array_values(array_filter($daftar, function ($chat) use ($nomorDiizinkan) {
        return in_array($chat['nomorId'], $nomorDiizinkan);
    }));

    // Menggabungkan pesan dari database ke percakapan yang sesuai
    foreach ($pesanTersimpan as $pesanDB) {
        foreach ($daftar as $index => $chat) {
            if (
                (int) $chat['nomorId'] === (int) $pesanDB->nomor_whatsapp_id &&
                $chat['telepon'] === $pesanDB->kontak_telepon
            ) {
                $daftar[$index]['pesan'][] = [
                    'pengirim' => $pesanDB->pengirim,
                    'isi' => $pesanDB->isi,
                    'jam' => $pesanDB->created_at
                        ? $pesanDB->created_at->format('H:i')
                        : '',
                ];

                $daftar[$index]['cuplikan'] = $pesanDB->isi;
                $daftar[$index]['jam'] = $pesanDB->created_at
                    ? $pesanDB->created_at->format('H:i')
                    : '';

                break;
            }
        }
    }

    // Menentukan nomor WhatsApp yang boleh digunakan untuk membalas
    if ($penggunaLogin->role === 'Administrator') {
        $nomorBolehBalas = $nomorDiizinkan;
    } else {
        $nomorBolehBalas = $penggunaLogin->nomorWhatsapp()
            ->wherePivotIn('hak_akses', ['Balas chat', 'Kelola'])
            ->pluck('nomor_whatsapp.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // Memilih kembali percakapan setelah pesan dikirim
    $chatTerpilih = session('chat_terpilih');

    $chatAwal = null;

    if ($chatTerpilih) {
        foreach ($daftar as $chat) {
            if (
                (int) $chat['nomorId'] === (int) $chatTerpilih['nomorId'] &&
                $chat['telepon'] === $chatTerpilih['telepon']
            ) {
                $chatAwal = $chat;
                break;
            }
        }
    }

    $chatAwal = $chatAwal ?? ($daftar[0] ?? null);
@endphp

@if (session('success'))
    <div class="mb-3 rounded-lg bg-green-50 border border-green-200 px-3 py-2 text-xs text-green-700">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div
    x-data="{
        takeover: false,
        tab: 'semua',
        selectedNomor: '',
        selectedChat: @js($chatAwal),
        nomorBolehBalas: @js($nomorBolehBalas),

        bolehBalas(nomorId) {
            return this.nomorBolehBalas.includes(Number(nomorId));
        },

        pilihChat(chat) {
            this.selectedChat = chat;
            this.takeover = false;
        },

        filterNomor(nomor) {
            this.selectedNomor = nomor;

            if (
                this.selectedChat &&
                nomor !== '' &&
                String(this.selectedChat.nomorId) !== String(nomor)
            ) {
                this.selectedChat = null;
            }

            if (nomor === '') {
                this.selectedChat = @js($chatAwal);
            }
        }
    }"
    @nomor-filter.window="filterNomor($event.detail)"
    class="grid grid-cols-[250px_minmax(0,1fr)_220px] gap-3 h-[calc(100vh-130px)] min-h-0 text-xs"
>

    {{-- KOLOM 1: DAFTAR PERCAKAPAN --}}
    <div class="card p-0 overflow-y-auto min-w-0">

        <div class="flex gap-3 px-3 py-2.5 text-[11px] border-b border-slate-100 whitespace-nowrap">

            <button @click="tab='semua'"
                :class="tab==='semua' ? 'text-teal-700 font-medium' : 'text-slate-500'">
                Semua
            </button>

            <button @click="tab='belum'"
                :class="tab==='belum' ? 'text-teal-700 font-medium' : 'text-slate-500'">
                Belum dibalas (3)
            </button>

            <button @click="tab='ai'"
                :class="tab==='ai' ? 'text-teal-700 font-medium' : 'text-slate-500'">
                Ditangani AI
            </button>

        </div>

        @foreach ($daftar as $chat)

            <div
                x-show="selectedNomor === '' || selectedNomor === '{{ $chat['nomorId'] }}'"
                @click="pilihChat(@js($chat))"
                :class="selectedChat && selectedChat.id === {{ $chat['id'] }} ? 'bg-teal-50/60' : 'hover:bg-slate-50'"
                class="px-3 py-2.5 border-b border-slate-100 cursor-pointer"
            >

                <div class="flex justify-between items-center gap-2">
                    <span class="font-medium text-xs truncate">
                        {{ $chat['nama'] }}
                    </span>

                    <span class="text-[10px] text-slate-400 shrink-0">
                        {{ $chat['jam'] }}
                    </span>
                </div>

                <div class="text-[11px] text-slate-500 truncate mt-1">
                    {{ $chat['cuplikan'] }}
                </div>

                <div class="mt-1.5 flex gap-1 flex-wrap">
                    <span class="badge {{ $chat['warna'] }} text-[10px]">
                        {{ $chat['nomor'] }}
                    </span>

                    <span class="badge {{ $chat['warna'] }} text-[10px]">
                        {{ $chat['status'] }}
                    </span>
                </div>

            </div>

        @endforeach

        @if (count($daftar) === 0)
            <div class="p-4 text-center text-[11px] text-slate-400">
                Belum ada percakapan yang dapat diakses.
            </div>
        @endif

    </div>


    {{-- KOLOM 2: AREA CHAT --}}
    <div class="card p-0 flex flex-col min-w-0 overflow-hidden">

        <template x-if="selectedChat">

            <div class="flex flex-col min-h-0 flex-1">

                {{-- HEADER CHAT --}}
                <div class="flex items-center justify-between px-3 py-2.5 border-b border-slate-100 gap-2">

                    <div class="min-w-0">
                        <div class="font-semibold text-xs" x-text="selectedChat.nama"></div>

                        <div class="text-[10px] text-slate-400 mt-0.5">
                            <span x-text="selectedChat.telepon"></span>
                            ·
                            <span x-text="selectedChat.nomor"></span>
                        </div>
                    </div>

                    <div class="flex gap-1.5 shrink-0">

                        <button class="btn btn-outline text-[11px] px-2 py-1.5">
                            Selesaikan
                        </button>

                        <button
                            @click="takeover = !takeover"
                            class="btn btn-primary text-[11px] px-2 py-1.5"
                            x-text="takeover ? 'Kembalikan ke AI' : 'Ambil alih'"
                        ></button>

                    </div>

                </div>


                {{-- ISI CHAT --}}
                <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-slate-50/50">

                    <template x-for="(pesan, index) in selectedChat.pesan" :key="index">

                        <div>

                            {{-- PESAN PELANGGAN --}}
                            <template x-if="pesan.pengirim === 'pelanggan'">
                                <div class="max-w-[75%] bg-white border border-slate-200 rounded-lg p-2.5">

                                    <div class="text-xs leading-relaxed" x-text="pesan.isi"></div>

                                    <div class="text-[10px] text-slate-400 mt-1.5" x-text="pesan.jam"></div>

                                </div>
                            </template>

                            {{-- BALASAN AI --}}
                            <template x-if="pesan.pengirim === 'ai'">
                                <div class="max-w-[75%] ml-auto bg-teal-50 border border-teal-100 rounded-lg p-2.5">

                                    <div class="text-xs leading-relaxed" x-text="pesan.isi"></div>

                                    <div class="text-[10px] text-slate-400 mt-1.5" x-text="pesan.jam + ' · SAPA AI'"></div>

                                </div>
                            </template>

                            {{-- BALASAN AGEN --}}
                            <template x-if="pesan.pengirim === 'agen'">
                                <div class="max-w-[75%] ml-auto bg-teal-50 border border-teal-100 rounded-lg p-2.5">

                                    <div class="text-xs leading-relaxed" x-text="pesan.isi"></div>

                                    <div class="text-[10px] text-slate-400 mt-1.5"
                                         x-text="pesan.jam + ' · {{ $penggunaLogin->name }}'">
                                    </div>

                                </div>
                            </template>

                            {{-- NOTIFIKASI SISTEM --}}
                            <template x-if="pesan.pengirim === 'sistem'">
                                <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-[11px] p-2.5"
                                     x-text="pesan.isi">
                                </div>
                            </template>

                        </div>

                    </template>

                </div>


                {{-- INPUT PESAN --}}
                <div class="p-2.5 border-t border-slate-100">

                    <form
                        method="POST"
                        action="{{ route('kotak-masuk.kirim') }}"
                        class="flex gap-2"
                        x-show="bolehBalas(selectedChat.nomorId)"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="nomor_whatsapp_id"
                            :value="selectedChat.nomorId"
                        >

                        <input
                            type="hidden"
                            name="kontak_nama"
                            :value="selectedChat.nama"
                        >

                        <input
                            type="hidden"
                            name="kontak_telepon"
                            :value="selectedChat.telepon"
                        >

                        <input
                            type="text"
                            name="isi"
                            class="field text-xs py-2 min-w-0"
                            placeholder="Ketik balasan..."
                            maxlength="5000"
                            required
                            autocomplete="off"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary text-xs px-3 py-2 shrink-0"
                        >
                            Kirim
                        </button>

                    </form>

                    <div
                        x-show="!bolehBalas(selectedChat.nomorId)"
                        class="text-center text-[11px] text-slate-400 py-2"
                    >
                        Kamu hanya memiliki izin melihat percakapan ini.
                    </div>

                </div>

            </div>

        </template>

        <div
            x-show="!selectedChat"
            class="flex-1 flex items-center justify-center text-xs text-slate-400 p-4 text-center"
        >
            Pilih percakapan dari daftar untuk melihat isi chat.
        </div>

    </div>


    {{-- KOLOM 3: KONTEKS PELANGGAN --}}
    <div class="card p-3 overflow-y-auto min-w-0 space-y-3">

        <template x-if="selectedChat">

            <div class="space-y-3">

                {{-- INFORMASI PELANGGAN --}}
                <div>

                    <div class="font-semibold text-[13px] mb-2">
                        Konteks pelanggan
                    </div>

                    <template x-for="item in [
                        ['Jenjang', selectedChat.jenjang],
                        ['Mata pelajaran', selectedChat.mapel],
                        ['Cabang', selectedChat.cabang],
                        ['Sumber', selectedChat.sumber],
                        ['Tahap prospek', selectedChat.tahap]
                    ]" :key="item[0]">

                        <div class="flex justify-between gap-2 py-1 text-[11px]">

                            <span class="text-slate-500" x-text="item[0]"></span>

                            <span class="font-medium text-right" x-text="item[1]"></span>

                        </div>

                    </template>

                </div>


                {{-- SKOR PRIORITAS --}}
                <div class="text-[11px] border-t border-slate-100 pt-2.5">

                    <span class="text-slate-500">
                        Skor prioritas:
                    </span>

                    <span class="font-semibold" x-text="selectedChat.skor"></span>

                </div>


                {{-- KEPUTUSAN AI --}}
                <div class="border-t border-slate-100 pt-2.5">

                    <div class="font-semibold text-[13px] mb-2">
                        Keputusan AI
                    </div>

                    <template x-for="item in [
                        ['Maksud', selectedChat.maksud],
                        ['Keyakinan', selectedChat.keyakinan],
                        ['Ambang eskalasi', selectedChat.ambang],
                        ['Artikel terkait', selectedChat.artikel]
                    ]" :key="item[0]">

                        <div class="flex justify-between gap-2 py-1 text-[11px]">

                            <span class="text-slate-500" x-text="item[0]"></span>

                            <span class="font-medium text-right break-all" x-text="item[1]"></span>

                        </div>

                    </template>

                </div>

            </div>

        </template>

        <div
            x-show="!selectedChat"
            class="text-center text-[11px] text-slate-400 py-4"
        >
            Informasi pelanggan akan ditampilkan setelah memilih percakapan.
        </div>

    </div>

</div>
@endsection
