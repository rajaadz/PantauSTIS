<?php

declare(strict_types=1);

/**
 * bootstrap.php
 * ---------------------------------------------------------------------
 * Di-require di baris pertama setiap endpoint di folder api/. Menyiapkan
 * koneksi PDO ($pdo, dari config/koneksi.php), sesi PHP (dari
 * config/session.php -- dipakai oleh api/session_status.php untuk
 * memberi tahu frontend statis apakah admin sedang login), header JSON,
 * pembatasan method HTTP, dan dua helper respons supaya format output
 * konsisten di semua endpoint (Dashboard, Tabel Pesaing, Detail Riwayat
 * Nilai, status login).
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// Semua endpoint di sini bersifat Read-only sesuai cakupan saat ini.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode tidak diizinkan. Endpoint ini hanya menerima GET.']);
    exit;
}

/**
 * Mengirim payload sebagai JSON dengan status HTTP tertentu, lalu
 * menghentikan eksekusi. Dipakai di setiap endpoint supaya satu request
 * selalu berakhir dengan satu respons JSON yang konsisten.
 */
function kirim_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Pembungkus kirim_json() khusus untuk kondisi error. */
function kirim_error(string $pesan, int $statusCode = 400): void
{
    kirim_json(['error' => $pesan], $statusCode);
}
