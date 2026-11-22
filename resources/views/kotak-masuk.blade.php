
@extends('layouts.app')
@section('title', 'Kotak Masuk')
@section('subtitle', 'Percakapan WhatsApp dari semua nomor')

@section('actions')
    {{-- x-data diperlukan agar Alpine memproses @change dan @click di header --}}
    <div x-data class="flex items-center gap-2">
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

    @if (count($nomorBolehBalas) > 0)
        <button type="button" class="btn btn-primary text-xs py-1.5"
            @click="$dispatch('pesan-baru')">
            Pesan baru
        </button>
    @endif
    </div>
@endsection

@section('content')


{{-- Notifikasi sukses sudah ditampilkan oleh layout. --}}

@if ($errors->any())
    <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div
    x-data="{
        tab: 'semua',
        selectedNomor: '',
        selectedChat: @js($chatAwal),
        nomorBolehBalas: @js($nomorBolehBalas),

        urlStatus(id) {
            return '{{ route('kotak-masuk.status', ['percakapan' => '__ID__']) }}'.replace('__ID__', id);
        },

        bolehBalas(nomorId) {
            return this.nomorBolehBalas.includes(Number(nomorId));
        },

        pilihChat(chat) {
            this.selectedChat = chat;
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
                Belum dibalas ({{ $jumlahBelumDibalas }})
            </button>

            <button @click="tab='ai'"
                :class="tab==='ai' ? 'text-teal-700 font-medium' : 'text-slate-500'">
                Ditangani AI
            </button>

        </div>

        @foreach ($daftar as $chat)

            <div
                x-show="(selectedNomor === '' || selectedNomor === '{{ $chat['nomorId'] }}')
                    && (tab === 'semua'
                        || (tab === 'belum' && @js($chat['belumDibalas']))
                        || (tab === 'ai' && @js($chat['statusAsli'] === 'Ditangani AI')))"
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

                    <div class="flex gap-1.5 shrink-0" x-show="bolehBalas(selectedChat.nomorId)">

                        <form method="POST" :action="urlStatus(selectedChat.id)">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="Selesai">
                            <button
                                class="btn btn-outline text-[11px] px-2 py-1.5"
                                :disabled="selectedChat.statusAsli === 'Selesai'"
                            >
                                Selesaikan
                            </button>
                        </form>

                        <form method="POST" :action="urlStatus(selectedChat.id)">
                            @csrf
                            @method('PATCH')
                            <input
                                type="hidden"
                                name="status"
                                :value="selectedChat.statusAsli === 'Ditangani Agen' ? 'Ditangani AI' : 'Ditangani Agen'"
                            >
                            <button
                                class="btn btn-primary text-[11px] px-2 py-1.5"
                                x-text="selectedChat.statusAsli === 'Ditangani Agen' ? 'Kembalikan ke AI' : 'Ambil alih'"
                            ></button>
                        </form>

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
                            name="percakapan_id"
                            :value="selectedChat.id"
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

{{-- MODAL PESAN BARU --}}
@if (count($nomorBolehBalas) > 0)
<div x-data="{ buka: {{ $errors->has('telepon') || $errors->has('isi') || $errors->has('nomor_whatsapp_id') ? 'true' : 'false' }} }"
    @pesan-baru.window="buka = true"
    x-show="buka" x-cloak
    class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">

    <div @click.outside="buka = false" class="bg-white rounded-xl p-4 w-full max-w-sm">
        <h3 class="font-semibold text-sm mb-3">Pesan baru</h3>

        <form method="POST" action="{{ route('kotak-masuk.baru') }}" class="space-y-3">
            @csrf

            <div>
                <label class="block text-[10px] text-slate-500 mb-1">Kirim dari nomor</label>
                <select name="nomor_whatsapp_id" class="field w-full !text-xs" required>
                    @foreach ($nomorWhatsapp->whereIn('id', $nomorBolehBalas) as $nomor)
                        <option value="{{ $nomor->id }}" @selected(old('nomor_whatsapp_id') == $nomor->id)>{{ $nomor->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] text-slate-500 mb-1">Nomor tujuan</label>
                <input type="text" name="telepon" value="{{ old('telepon') }}" required
                    placeholder="Contoh: 081234567890" class="field w-full !text-xs">
            </div>

            <div>
                <label class="block text-[10px] text-slate-500 mb-1">Nama (opsional)</label>
                <input type="text" name="nama" value="{{ old('nama') }}" class="field w-full !text-xs">
            </div>

            <div>
                <label class="block text-[10px] text-slate-500 mb-1">Pesan</label>
                <textarea name="isi" rows="3" required maxlength="5000" class="field w-full !text-xs">{{ old('isi') }}</textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" @click="buka = false" class="btn btn-outline !text-[10px] !px-2.5 !py-1">Batal</button>
                <button type="submit" class="btn btn-primary !text-[10px] !px-2.5 !py-1">Kirim</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
