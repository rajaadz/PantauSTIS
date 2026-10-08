<?php

declare(strict_types=1);

/**
 * GET api/riwayat.php
 * Seluruh riwayat pendaftaran + nilai (gabungan riwayat_metrik_kelulusan
 * dan riwayat_nilai), dipakai oleh Tabel Pesaing dan Detail Riwayat Nilai.
 * Bentuk output PERSIS mengikuti assets/data/riwayat.json (mock) supaya
 * frontend tidak perlu diubah sama sekali -- tinggal ganti
 * CONFIG.useMockData jadi false di assets/js/app.js.
 *
 * CATATAN DESAIN -- kenapa LEFT JOIN, bukan pakai view v_riwayat_nilai_lengkap:
 * View itu di tracker_spmb_stis.sql memakai JOIN biasa (INNER) antara
 * riwayat_metrik_kelulusan dan riwayat_nilai, jadi kalau admin sudah
 * input jumlah pendaftar/kuota tapi BELUM sempat input nilai SKD/
 * Matematika-nya, baris itu akan hilang total dari daftar -- padahal
 * datanya sebenarnya ada, cuma belum lengkap. Di sini query dibuat
 * langsung dari tabel dasar dengan LEFT JOIN ke riwayat_nilai, supaya
 * baris seperti itu tetap muncul dengan "nilai" yang tidak lengkap
 * (bukan disembunyikan diam-diam). Frontend (detail.html) sudah
 * disesuaikan untuk menampilkan status "belum diinput" kalau ini terjadi.
 */

require_once __DIR__ . '/bootstrap.php';

$stmt = $pdo->query('
    SELECT
        m.tahun,
        p.kode_prodi,
        v.kode_provinsi,
        m.jumlah_pendaftar,
        m.kuota,
        m.jumlah_lulus,
        n.jenis_ujian,
        n.nilai_tertinggi,
        n.nilai_terendah,
        n.nilai_rata_rata
    FROM riwayat_metrik_kelulusan m
    JOIN program_studi p ON p.id_prodi = m.id_prodi
    JOIN provinsi v ON v.id_provinsi = m.id_provinsi
    LEFT JOIN riwayat_nilai n ON n.id_metrik = m.id_metrik
    ORDER BY m.tahun, p.kode_prodi, v.kode_provinsi
');

$grouped = [];

foreach ($stmt as $row) {
    $key = $row['tahun'] . '|' . $row['kode_prodi'] . '|' . $row['kode_provinsi'];

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'tahun'            => (int) $row['tahun'],
            'kode_prodi'       => $row['kode_prodi'],
            'kode_provinsi'    => $row['kode_provinsi'],
            'jumlah_pendaftar' => (int) $row['jumlah_pendaftar'],
            'kuota'            => (int) $row['kuota'],
            'jumlah_lulus'     => (int) $row['jumlah_lulus'],
            // Objek nilai bisa berisi 0, 1, atau 2 kunci (SKD / Matematika)
            // tergantung apa yang sudah diinput admin -- lihat catatan di atas.
            'nilai'            => [],
        ];
    }

    if ($row['jenis_ujian'] !== null) {
        $grouped[$key]['nilai'][$row['jenis_ujian']] = [
            'tertinggi' => (float) $row['nilai_tertinggi'],
            'terendah'  => (float) $row['nilai_terendah'],
            'rata_rata' => (float) $row['nilai_rata_rata'],
        ];
    }
}

// Paksa "nilai" selalu ter-encode sebagai objek JSON ({}), bukan array
// ([]), walau isinya kosong -- supaya bentuknya konsisten dari sisi
// frontend (row.nilai.SKD / row.nilai.Matematika), bukan kadang objek
// kadang array tergantung ada-tidaknya data.
foreach ($grouped as &$item) {
    $item['nilai'] = (object) $item['nilai'];
}
unset($item);

kirim_json(['data' => array_values($grouped)]);
