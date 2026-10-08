<?php

declare(strict_types=1);

/**
 * GET api/provinsi.php
 * Daftar semua provinsi -- dipakai untuk menerjemahkan kode_provinsi
 * jadi nama_provinsi di Tabel Pesaing dan Detail Riwayat Nilai.
 *
 * Diurutkan berdasarkan kode_provinsi (kode BPS, 11-94), BUKAN alfabet
 * nama provinsi -- supaya urutannya konsisten dengan urutan resmi BPS
 * dan dengan dropdown provinsi di kelola_data.php. Kode "Pusat"/K/L/D/I
 * lain sengaja belum ada di tabel provinsi (lihat catatan di
 * tracker_spmb_stis.sql bagian 2.3), jadi belum perlu penanganan khusus.
 */

require_once __DIR__ . '/bootstrap.php';

$stmt = $pdo->query('
    SELECT kode_provinsi, nama_provinsi
    FROM provinsi
    ORDER BY kode_provinsi
');

kirim_json(['data' => $stmt->fetchAll()]);
