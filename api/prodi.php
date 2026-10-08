<?php

declare(strict_types=1);

/**
 * GET api/prodi.php
 * Daftar semua program studi -- dipakai Dashboard Utama untuk merender
 * kartu prodi. Tidak ada input dari pengguna di sini, jadi cukup
 * $pdo->query() biasa (tidak perlu prepared statement).
 */

require_once __DIR__ . '/bootstrap.php';

$stmt = $pdo->query('
    SELECT kode_prodi, nama_prodi, jenjang, deskripsi
    FROM program_studi
    ORDER BY jenjang, nama_prodi
');

kirim_json(['data' => $stmt->fetchAll()]);
