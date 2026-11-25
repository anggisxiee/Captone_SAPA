<?php

return [

    /*
    | Driver klien AI.
    |   fake   : simulasi tanpa penyedia sungguhan (tes dan pengembangan tanpa kunci).
    |   openai : penyedia kompatibel OpenAI (POST {base_url}/chat/completions).
    |            Dipakai untuk SumoPod (https://ai.sumopod.com/v1), OpenAI, dan sejenisnya.
    */
    'driver' => env('AI_DRIVER', 'fake'),

    /*
    | Nilai bawaan penyedia. Bila Administrator sudah menyimpan kunci API di halaman
    | Pengaturan AI, seluruh nilai dari halaman itu dipakai dan nilai di sini diabaikan.
    */
    'base_url' => env('AI_BASE_URL', 'https://ai.sumopod.com/v1'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'api_key' => env('AI_API_KEY'),

    // Batas waktu satu pemanggilan (detik). Lewat batas ini sistem beralih ke mode aturan bawaan.
    'timeout' => (int) env('AI_TIMEOUT', 20),

    // Memproses pesan pelanggan yang masuk dengan mesin jawaban (saran atau balasan otomatis,
    // sesuai mode AI tiap nomor). Matikan untuk menjalankan SAPA tanpa AI.
    'proses_pesan_masuk' => (bool) env('AI_PROSES_PESAN_MASUK', true),

    // Pengaman sementara sebelum mesin anti-ban (Sprint 3): jumlah balasan otomatis AI per
    // percakapan per jam. Lewat batas ini AI berhenti dan percakapan diserahkan ke agen.
    'maks_balasan_per_jam' => (int) env('AI_MAKS_BALASAN_PER_JAM', 20),

    // Artikel dengan skor relevansi di bawah ambang ini tidak diberikan ke AI. Sengaja longgar
    // (hasil kalibrasi: 0,50 menjawab lebih banyak tanpa menambah halusinasi), karena AI menilai
    // sendiri kecukupan artikel ("cukup") dan keyakinan akhir masih diperiksa terhadap ambang.
    'ambang_relevansi' => (float) env('AI_AMBANG_RELEVANSI', 0.50),

    // Saat penyedia AI tidak dapat dipakai, jawaban dikutip dari artikel apa adanya tanpa ada yang
    // menilai kecukupannya, jadi hanya kecocokan yang kuat yang dipakai.
    'ambang_relevansi_aturan' => (float) env('AI_AMBANG_RELEVANSI_ATURAN', 0.70),
];
