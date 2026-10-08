<?php

declare(strict_types=1);

/**
 * GET api/cari_provinsi.php?q=<kata kunci>
 * Endpoint live-search untuk kotak "Cari provinsi" di Tabel Pesaing.
 * Dipanggil oleh assets/js/live-search.js lewat fetch(), sama seperti
 * cari_prodi.php.
 */

require_once __DIR__ . '/bootstrap.php';

$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if ($query === '') {
    kirim_json(['data' => []]);
}

$likeParam = '%' . $query . '%';

$stmt = $pdo->prepare('
    SELECT kode_provinsi, nama_provinsi
    FROM provinsi
    WHERE nama_provinsi LIKE :q
    ORDER BY nama_provinsi
    LIMIT 20
');
$stmt->execute([':q' => $likeParam]);

kirim_json(['data' => $stmt->fetchAll()]);
