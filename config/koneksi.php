<?php

declare(strict_types=1);

/**
 * koneksi.php
 * ---------------------------------------------------------------------
 * Koneksi database (PDO) untuk Tracker SPMB STIS (PantauSTIS).
 * File ini HANYA berisi koneksi -- tidak ada logika query di sini,
 * supaya bisa di-require dari mana saja (folder api/, halaman admin
 * nanti, dst) tanpa duplikasi kode koneksi.
 *
 * Cara pakai:
 *   require_once __DIR__ . '/../config/koneksi.php';
 *   // variabel $pdo sudah siap dipakai di file yang meng-include ini
 *
 * SIAP DI-HOSTING:
 *   Empat konstanta DB_* di bawah adalah satu-satunya bagian yang perlu
 *   diganti saat pindah ke hosting/server produksi. Untuk keamanan yang
 *   lebih baik di produksi, sebaiknya nilai ini diambil dari environment
 *   variable (getenv('DB_HOST'), dst) alih-alih ditulis langsung di
 *   kode, supaya kredensial tidak ikut ter-commit ke repository.
 * ---------------------------------------------------------------------
 */

const DB_HOST    = 'localhost';
const DB_NAME    = 'tracker_spmb_stis';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// APP_DEBUG = true  -> pesan error PDO ditampilkan apa adanya (memudahkan
//                      development lokal, tapi BISA membocorkan detail
//                      struktur database ke pengunjung).
// APP_DEBUG = false -> pesan error generik ke pengunjung, detail asli
//                      dicatat lewat error_log() di server.
// WAJIB diganti ke false sebelum di-deploy ke hosting publik.
const APP_DEBUG = true;

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    // Error PDO dilempar sebagai exception, bukan diam-diam gagal --
    // supaya masalah query tidak pernah tertelan tanpa disadari.
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

    // Hasil query default berbentuk associative array (bukan objek
    // atau array bernomor), sesuai bentuk JSON yang dipakai frontend.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Prepared statement betulan di level driver MySQL (bukan disimulasikan
    // oleh PDO dengan menyisipkan nilai ke string SQL). Ini juga yang
    // membuat parameter query benar-benar aman dari SQL Injection.
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    if (APP_DEBUG) {
        echo json_encode([
            'error'  => 'Koneksi database gagal.',
            'detail' => $e->getMessage(),
        ]);
    } else {
        error_log('[koneksi.php] Koneksi database gagal: ' . $e->getMessage());
        echo json_encode(['error' => 'Terjadi kesalahan pada server. Silakan coba lagi nanti.']);
    }

    exit;
}
