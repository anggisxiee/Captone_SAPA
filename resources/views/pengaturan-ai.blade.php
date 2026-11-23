
@extends('layouts.app')

@section('title', 'Pengaturan AI')
@section('subtitle', 'Penyedia model, ambang keyakinan, eskalasi, dan batas anti-ban')

@section('actions')
    <form action="{{ route('pengaturan-ai.reset') }}" method="POST"
          onsubmit="return confirm('Yakin ingin mengembalikan semua pengaturan AI ke bawaan?')">
        @csrf
        <button type="submit" class="btn btn-outline !text-[10px] !px-3 !py-1.5">
            Reset ke bawaan
        </button>
    </form>

    <button type="submit" form="formPengaturanAI"
            class="btn btn-primary !text-[10px] !px-3 !py-1.5">
        Simpan pengaturan
    </button>
@endsection

@section('content')

@if (session('peringatan'))
    <div class="mb-3 rounded-md bg-amber-50 text-amber-700 text-xs p-2.5">
        {{ session('peringatan') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-3 rounded-md bg-red-50 text-red-700 text-xs p-2.5">
        <div class="font-semibold mb-1">Pengaturan gagal disimpan:</div>
        <ul class="list-disc pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-[minmax(0,1fr)_260px] gap-3 text-xs">

    {{-- KOLOM KIRI: PENGATURAN AI --}}
    <form id="formPengaturanAI"
          action="{{ route('pengaturan-ai.update') }}"
          method="POST"
          class="space-y-3">

        @csrf
        @method('PUT')

        {{-- Penyedia AI --}}
        <div class="card !p-3 space-y-2.5">
            <div class="font-semibold text-xs">Penyedia AI</div>

            <div class="grid grid-cols-2 gap-x-3 gap-y-2">

                <div>
                    <label class="label !text-[10px]">Penyedia</label>
                    <select name="penyedia" class="field !text-[10px] !py-1.5">
                        <option value="OpenAI-compatible"
                            @selected(old('penyedia', $pengaturanAI->penyedia) === 'OpenAI-compatible')>
                            OpenAI-compatible
                        </option>
                        <option value="Gemini"
                            @selected(old('penyedia', $pengaturanAI->penyedia) === 'Gemini')>
                            Gemini
                        </option>
                        <option value="Mode aturan bawaan"
                            @selected(old('penyedia', $pengaturanAI->penyedia) === 'Mode aturan bawaan')>
                            Mode aturan bawaan
                        </option>
                    </select>
                </div>

                <div>
                    <label class="label !text-[10px]">Model</label>
                    <input type="text" name="model"
                           class="field !text-[10px] !py-1.5"
                           value="{{ old('model', $pengaturanAI->model) }}">
                </div>

                <div>
                    <label class="label !text-[10px]">Kunci API</label>
                    <input type="password" name="api_key"
                           class="field !text-[10px] !py-1.5"
                           placeholder="{{ $pengaturanAI->api_key ? 'API Key sudah tersimpan (kosongkan jika tidak diubah)' : 'Masukkan API Key' }}"
                           autocomplete="new-password">

                    <p class="text-[9px] text-slate-400 mt-1">
                        Disimpan terenkripsi. Kosongkan jika tidak ingin mengubah kunci.
                        Kunci baru diuji ke penyedia sebelum disimpan.
                    </p>
                    <p class="text-[9px] mt-1 {{ $konfigurasi->apiKey ? 'text-emerald-600' : 'text-amber-600' }}">
                        @if ($konfigurasi->driver === 'fake')
                            Driver AI: simulasi (AI_DRIVER=fake), kunci tidak dipakai.
                        @elseif ($konfigurasi->apiKey)
                            Kunci yang dipakai: {{ $konfigurasi->kunciSamar() }}
                            ({{ $konfigurasi->sumber === 'pengaturan' ? 'dari halaman ini' : 'dari .env' }}).
                        @else
                            Belum ada kunci API: sistem memakai mode aturan bawaan.
                        @endif
                    </p>
                </div>

                <div>
                    <label class="label !text-[10px]">Base URL</label>
                    <input type="text" name="base_url"
                           class="field !text-[10px] !py-1.5"
                           value="{{ old('base_url', $pengaturanAI->base_url) }}">
                </div>

            </div>

            <div>
                <label class="label !text-[10px]">Instruksi persona</label>
                <textarea name="instruksi_persona"
                          class="field !text-[10px] !py-2"
                          rows="2">{{ old('instruksi_persona', $pengaturanAI->instruksi_persona) }}</textarea>
            </div>
        </div>


        {{-- Ambang Keyakinan dan Eskalasi --}}
        <div class="card !p-3 space-y-2.5">

            <div class="font-semibold text-xs">
                Ambang keyakinan dan eskalasi
            </div>

            <div class="grid grid-cols-2 gap-3">

                <div>
                    <label class="label !text-[10px]">
                        Ambang keyakinan minimum
                    </label>

                    <input type="number" name="ambang_keyakinan"
                           class="field !text-[10px] !py-1.5"
                           min="0" max="1" step="0.01"
                           value="{{ old('ambang_keyakinan', $pengaturanAI->ambang_keyakinan) }}">

                    <p class="text-[9px] text-slate-400 mt-1 leading-relaxed">
                        Di bawah nilai ini AI tidak membalas dan percakapan dieskalasi ke agen.
                    </p>
                </div>

                <div>
                    <label class="label !text-[10px]">
                        Eskalasi bila pelanggan marah
                    </label>

                    <select name="eskalasi_pelanggan_marah"
                            class="field !text-[10px] !py-1.5">
                        <option value="1"
                            @selected((string) old('eskalasi_pelanggan_marah', (int) $pengaturanAI->eskalasi_pelanggan_marah) === '1')>
                            Aktif
                        </option>
                        <option value="0"
                            @selected((string) old('eskalasi_pelanggan_marah', (int) $pengaturanAI->eskalasi_pelanggan_marah) === '0')>
                            Nonaktif
                        </option>
                    </select>
                </div>

            </div>

            <div>
                <label class="label !text-[10px]">
                    Kata kunci eskalasi
                </label>

                <input type="text" name="kata_kunci_eskalasi"
                       class="field !text-[10px] !py-1.5"
                       value="{{ old('kata_kunci_eskalasi', $pengaturanAI->kata_kunci_eskalasi) }}"
                       placeholder="admin, komplain, refund, bicara manusia, biaya mahal">

                <p class="text-[9px] text-slate-400 mt-1">
                    Pisahkan setiap kata kunci dengan tanda koma.
                </p>
            </div>

        </div>


        {{-- Anti-ban --}}
        <div class="card !p-3 space-y-2.5">

            <div class="font-semibold text-xs">Anti-ban</div>

            <div class="grid grid-cols-2 gap-x-3 gap-y-2">

                <div>
                    <label class="label !text-[10px]">Jeda minimum (detik)</label>
                    <input type="number" name="jeda_min"
                           class="field !text-[10px] !py-1.5"
                           min="0"
                           value="{{ old('jeda_min', $pengaturanAI->jeda_min) }}">
                </div>

                <div>
                    <label class="label !text-[10px]">Jeda maksimum (detik)</label>
                    <input type="number" name="jeda_max"
                           class="field !text-[10px] !py-1.5"
                           min="0"
                           value="{{ old('jeda_max', $pengaturanAI->jeda_max) }}">
                </div>

                <div>
                    <label class="label !text-[10px]">
                        Batas pesan per hari per nomor
                    </label>
                    <input type="number" name="batas_pesan_harian"
                           class="field !text-[10px] !py-1.5"
                           min="1"
                           value="{{ old('batas_pesan_harian', $pengaturanAI->batas_pesan_harian) }}">
                </div>

                <div>
                    <label class="label !text-[10px]">Jam kirim</label>

                    <div class="flex items-center gap-1">
                        <input type="time" name="jam_mulai"
                               class="field !text-[10px] !py-1.5 min-w-0"
                               value="{{ old('jam_mulai', substr($pengaturanAI->jam_mulai, 0, 5)) }}">

                        <span class="text-slate-400">-</span>

                        <input type="time" name="jam_selesai"
                               class="field !text-[10px] !py-1.5 min-w-0"
                               value="{{ old('jam_selesai', substr($pengaturanAI->jam_selesai, 0, 5)) }}">
                    </div>
                </div>

                <div>
                    <label class="label !text-[10px]">
                        Variasi kalimat otomatis
                    </label>

                    <select name="variasi_kalimat"
                            class="field !text-[10px] !py-1.5">
                        <option value="1"
                            @selected((string) old('variasi_kalimat', (int) $pengaturanAI->variasi_kalimat) === '1')>
                            Aktif
                        </option>
                        <option value="0"
                            @selected((string) old('variasi_kalimat', (int) $pengaturanAI->variasi_kalimat) === '0')>
                            Nonaktif
                        </option>
                    </select>
                </div>

            </div>
        </div>

    </form>


    {{-- KOLOM KANAN: KONSOL UJI --}}
    <div class="card self-start !p-3 space-y-2.5">

        <div class="font-semibold text-xs">Konsol uji</div>

        <form id="formUjiAI"
              action="{{ route('pengaturan-ai.uji') }}"
              method="POST"
              class="space-y-2">

            @csrf

            <div>
                <label class="label !text-[10px]">Pesan uji</label>

                <textarea name="pesan"
                          id="pesanUjiAI"
                          class="field !text-[10px] !py-2"
                          rows="4"
                          placeholder="Masukkan pesan pelanggan..."
                          required>Halo kak, hasil tryout anak saya belum keluar. Kalau mau refund biaya gimana caranya?</textarea>
            </div>

            <button type="submit"
                    id="tombolUjiAI"
                    class="btn btn-primary w-full !text-[10px] !py-1.5">
                Jalankan uji
            </button>

        </form>

        <div class="font-semibold text-[10px] pt-1">
            Hasil keputusan
        </div>

        <div id="hasilUjiAI" class="text-[10px] space-y-2">
            <p class="text-slate-500">Belum diuji. Pesan diproses seperti pesan pelanggan sungguhan, tetapi tidak ada yang dikirim.</p>
        </div>

        <div id="statusUjiAI"
             class="hidden rounded-md text-[10px] leading-relaxed p-2.5">
        </div>

        <div class="rounded-md bg-slate-50 text-slate-500 text-[9px] leading-relaxed p-2.5">
            Uji ini memanggil penyedia AI sungguhan (memakai sedikit saldo), kecuali pesan sudah diputuskan
            oleh aturan gratis: kata kunci eskalasi, nada marah, atau tanpa artikel relevan.
        </div>

    </div>

</div>


{{-- JAVASCRIPT CONSOLE UJI --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('formUjiAI');
    const tombol = document.getElementById('tombolUjiAI');
    const hasil = document.getElementById('hasilUjiAI');
    const status = document.getElementById('statusUjiAI');

    if (!form) return;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        tombol.disabled = true;
        tombol.textContent = 'Sedang menguji...';

        status.classList.add('hidden');
        hasil.innerHTML = `
            <p class="text-slate-500">Sedang menganalisis pesan...</p>
        `;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Pengujian gagal. Periksa kembali pesan.'
                );
            }

            // Eskalasi oleh aturan (kata kunci / nada) memutuskan sebelum artikel dicari.
            const olehAturan = ['kata_kunci', 'nada_marah'].includes(data.alasan_kode);

            const artikel = olehAturan
                ? '<p class="text-slate-500">Tidak diperiksa (diputuskan lebih dulu oleh aturan).</p>'
                : (data.artikel || []).length
                ? data.artikel.map(a => `
                    <div class="flex justify-between gap-2">
                        <span class="min-w-0 break-words">${escapeHTML(a.kode)} ${escapeHTML(a.judul)}</span>
                        <span class="shrink-0 ${a.dipakai ? 'text-emerald-600' : 'text-red-500'}">${Number(a.skor).toFixed(2).replace('.', ',')}</span>
                    </div>`).join('')
                : '<p class="text-slate-500">Tidak ada artikel yang cocok.</p>';

            const pemeriksaan = data.kata_kunci
                ? `Kata kunci eskalasi: <b>${escapeHTML(data.kata_kunci)}</b>`
                : (data.nada && data.nada.marah
                    ? `Nada marah (${escapeHTML(data.nada.pemicu.join(', '))})`
                    : 'Tidak ada kata kunci eskalasi, nada tidak marah');

            const modeTeks = data.mode === 'ai'
                ? `AI (${escapeHTML(data.model || '-')})`
                : 'Aturan bawaan' + (data.galat ? ` , penyedia tidak dapat dipakai: ${escapeHTML(data.galat)}` : '');

            hasil.innerHTML = `
                <div>
                    <b>1. Maksud</b>
                    <p class="text-slate-500 mt-0.5">${escapeHTML(data.maksud || '-')}</p>
                </div>

                <div>
                    <b>2. Pemeriksaan aturan</b>
                    <p class="text-slate-500 mt-0.5">${pemeriksaan}</p>
                </div>

                <div>
                    <b>3. Artikel (ambang ${Number(data.ambang_relevansi).toFixed(2).replace('.', ',')})</b>
                    <div class="text-slate-500 mt-0.5 space-y-0.5">${artikel}</div>
                </div>

                <div>
                    <b>4. Keyakinan</b>
                    <p class="text-slate-500 mt-0.5">
                        ${olehAturan ? 'Tidak dihitung' : Number(data.keyakinan).toFixed(2).replace('.', ',')}
                        dari ambang ${Number(data.ambang).toFixed(2).replace('.', ',')}
                    </p>
                </div>

                <div>
                    <b>5. Keputusan</b>
                    <p class="mt-0.5 ${data.eskalasi ? 'text-red-600' : 'text-emerald-600'}">
                        ${data.eskalasi ? 'Eskalasi ke agen: ' + escapeHTML(data.alasan) : 'Kirim jawaban otomatis'}
                    </p>
                </div>

                <div>
                    <b>6. Yang diterima pelanggan</b>
                    <div class="mt-1 rounded-md bg-slate-50 p-2 text-slate-600 leading-relaxed whitespace-pre-line">${escapeHTML(data.pesan_pelanggan)}</div>
                    ${data.jawaban_ditolak ? `<p class="text-slate-400 mt-1">Jawaban yang ditolak (untuk dinilai): ${escapeHTML(data.jawaban_ditolak)}</p>` : ''}
                </div>

                <p class="text-slate-400 text-[9px]">
                    Mode: ${modeTeks}${data.token ? ` · ${data.token} token` : ''}${data.latensi_ms ? ` · ${data.latensi_ms} ms` : ''}
                </p>
            `;

            status.textContent = 'Pengujian berhasil.';
            status.className = 'rounded-md bg-emerald-50 text-emerald-700 text-[10px] p-2.5';

        } catch (error) {

            hasil.innerHTML = `
                <p class="text-red-600">${escapeHTML(error.message)}</p>
            `;

            status.textContent = 'Terjadi kesalahan saat menguji pesan.';
            status.className = 'rounded-md bg-red-50 text-red-700 text-[10px] p-2.5';

        } finally {
            tombol.disabled = false;
            tombol.textContent = 'Jalankan uji';
        }
    });

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

});
</script>

@endsection
