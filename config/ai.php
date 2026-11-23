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

    // Artikel dengan skor relevansi di bawah ambang ini tidak dipakai AI (mockup halaman Basis Pengetahuan).
    'ambang_relevansi' => (float) env('AI_AMBANG_RELEVANSI', 0.70),
];
