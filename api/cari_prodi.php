<?php

declare(strict_types=1);

/**
 * GET api/cari_prodi.php?q=<kata kunci>
 * Endpoint live-search untuk kotak "Cari program studi" di Dashboard
 * Utama. Dipanggil oleh assets/js/live-search.js lewat fetch(), dengan
 * query sudah di-debounce (250ms) di sisi frontend.
 *
 * Parameter :q_nama dan :q_kode di-bind terpisah (bukan satu named
 * parameter dipakai dua kali) karena PDO tidak mengizinkan satu nama
 * parameter dipakai berulang dalam satu prepared statement saat
 * PDO::ATTR_EMULATE_PREPARES di-nonaktifkan (lihat config/koneksi.php).
 */

require_once __DIR__ . '/bootstrap.php';

$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if ($query === '') {
    kirim_json(['data' => []]);
}

// Catatan: karakter '%' atau '_' yang diketik pengguna akan diperlakukan
// sebagai wildcard LIKE oleh MySQL (bukan dicari sebagai karakter literal).
// Ini bukan celah keamanan (parameter tetap ter-bind aman lewat prepared
// statement, jadi tidak mungkin jadi SQL Injection) -- hanya kuirk kecil
// pada hasil pencarian untuk kasus yang sangat jarang terjadi pada nama
// prodi/provinsi.
$likeParam = '%' . $query . '%';

$stmt = $pdo->prepare('
    SELECT kode_prodi, nama_prodi, jenjang
    FROM program_studi
    WHERE nama_prodi LIKE :q_nama OR kode_prodi LIKE :q_kode
    ORDER BY nama_prodi
    LIMIT 20
');
$stmt->execute([
    ':q_nama' => $likeParam,
    ':q_kode' => $likeParam,
]);

kirim_json(['data' => $stmt->fetchAll()]);
