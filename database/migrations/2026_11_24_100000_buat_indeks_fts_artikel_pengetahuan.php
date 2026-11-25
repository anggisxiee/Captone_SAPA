<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indeks pencarian teks penuh FTS5 untuk artikel_pengetahuan (peringkat bm25).
 *
 * Tabel FTS5 memakai "external content": teks tetap disimpan hanya di
 * artikel_pengetahuan, indeksnya dijaga sinkron oleh pemicu basis data. Dengan begitu
 * setiap perubahan artikel, lewat aplikasi maupun langsung ke basis data, otomatis
 * mengindeks ulang (BR12). Hanya untuk SQLite, sesuai SRS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE VIRTUAL TABLE artikel_pengetahuan_fts USING fts5(
                judul, isi, label,
                content='artikel_pengetahuan', content_rowid='id',
                tokenize='unicode61 remove_diacritics 2'
            )
        ");

        DB::unprepared("
            CREATE TRIGGER artikel_pengetahuan_fts_ai AFTER INSERT ON artikel_pengetahuan BEGIN
                INSERT INTO artikel_pengetahuan_fts(rowid, judul, isi, label)
                VALUES (new.id, new.judul, new.isi, coalesce(new.label, ''));
            END
        ");

        DB::unprepared("
            CREATE TRIGGER artikel_pengetahuan_fts_ad AFTER DELETE ON artikel_pengetahuan BEGIN
                INSERT INTO artikel_pengetahuan_fts(artikel_pengetahuan_fts, rowid, judul, isi, label)
                VALUES ('delete', old.id, old.judul, old.isi, coalesce(old.label, ''));
            END
        ");

        // Hanya kolom teks yang memicu pengindeksan ulang (mengubah status aktif tidak perlu).
        DB::unprepared("
            CREATE TRIGGER artikel_pengetahuan_fts_au AFTER UPDATE OF judul, isi, label ON artikel_pengetahuan BEGIN
                INSERT INTO artikel_pengetahuan_fts(artikel_pengetahuan_fts, rowid, judul, isi, label)
                VALUES ('delete', old.id, old.judul, old.isi, coalesce(old.label, ''));
                INSERT INTO artikel_pengetahuan_fts(rowid, judul, isi, label)
                VALUES (new.id, new.judul, new.isi, coalesce(new.label, ''));
            END
        ");

        // Artikel yang sudah ada sebelum indeks dibuat.
        DB::statement("INSERT INTO artikel_pengetahuan_fts(artikel_pengetahuan_fts) VALUES ('rebuild')");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS artikel_pengetahuan_fts_au');
        DB::unprepared('DROP TRIGGER IF EXISTS artikel_pengetahuan_fts_ad');
        DB::unprepared('DROP TRIGGER IF EXISTS artikel_pengetahuan_fts_ai');
        DB::statement('DROP TABLE IF EXISTS artikel_pengetahuan_fts');
    }
};
