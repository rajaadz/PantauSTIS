<?php

declare(strict_types=1);

/**
 * kelola_data.php
 * ---------------------------------------------------------------------
 * Halaman admin (diproteksi -- lihat require_login() di bawah) untuk
 * mengelola data riwayat SPMB STIS: Create, Update, dan Delete pada
 * riwayat_metrik_kelulusan + riwayat_nilai. Read (menampilkan data ke
 * pengguna umum) ditangani halaman lain yang sudah ada
 * (dashboard.html, pesaing.html, detail.html lewat api/*.php).
 *
 * ALUR HALAMAN INI
 *   1. Cek login (require_login) -- kalau belum, langsung redirect ke
 *      login.php dan berhenti, sebelum satu baris HTML pun dikirim.
 *   2. Tangani submit form (POST): hapus, atau simpan (tambah/edit).
 *      Selalu diakhiri redirect (pola Post/Redirect/Get) supaya form
 *      tidak ke-submit ulang kalau halaman di-refresh.
 *   3. Render halaman (GET): form Tambah/Edit + tabel seluruh riwayat.
 *
 * Satu <form> dipakai untuk Create MAUPUN Update (field tersembunyi
 * "id_metrik" menentukan mode: 0 = tambah baris baru, >0 = edit baris
 * yang sudah ada) -- supaya validasi & query tidak perlu ditulis dua
 * kali dengan logika yang nyaris sama.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/koneksi.php';

require_login();

// -----------------------------------------------------------------
// Validasi nilai form terhadap aturan yang sama seperti CHECK
// constraint di database (lihat tracker_spmb_stis.sql), supaya admin
// dapat pesan error yang jelas di sisi PHP dulu -- bukan cuma pesan
// teknis dari MySQL kalau constraint-nya baru ketahuan gagal di DB.
// -----------------------------------------------------------------
function validasi_riwayat(array $d): array
{
    $errors = [];

    if ($d['id_prodi'] <= 0) {
        $errors[] = 'Program studi wajib dipilih.';
    }
    if ($d['id_provinsi'] < 0) {
        $errors[] = 'Provinsi wajib dipilih.';
    }
    if ($d['tahun'] < 2000 || $d['tahun'] > 2100) {
        $errors[] = 'Tahun harus di antara 2000 dan 2100.';
    }
    if ($d['jumlah_pendaftar'] < 0 || $d['kuota'] < 0 || $d['jumlah_lulus'] < 0) {
        $errors[] = 'Jumlah pendaftar, kuota, dan jumlah lulus tidak boleh negatif.';
    }
    if ($d['jumlah_lulus'] > $d['jumlah_pendaftar']) {
        $errors[] = 'Jumlah lulus tidak boleh lebih besar dari jumlah pendaftar.';
    }

    $skalaNilai = ['SKD' => [0, 550], 'Matematika' => [0, 200]];
    foreach (['skd' => 'SKD', 'matematika' => 'Matematika'] as $key => $label) {
        [$min, $max] = $skalaNilai[$label];
        $tertinggi = $d[$key]['tertinggi'];
        $terendah  = $d[$key]['terendah'];
        $rata      = $d[$key]['rata_rata'];

        if ($tertinggi < $min || $tertinggi > $max || $terendah < $min || $terendah > $max || $rata < $min || $rata > $max) {
            $errors[] = "Nilai $label harus di antara $min dan $max.";
        } elseif (!($tertinggi >= $rata && $rata >= $terendah)) {
            $errors[] = "Nilai $label: nilai tertinggi harus \u{2265} rata-rata, dan rata-rata harus \u{2265} nilai terendah.";
        }
    }

    return $errors;
}

// -----------------------------------------------------------------
// 2. TANGANI SUBMIT FORM (POST)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $csrf   = (string) ($_POST['csrf_token'] ?? '');

    if (!csrf_verify($csrf)) {
        flash_set('error', 'Sesi form sudah kedaluwarsa (token tidak valid). Silakan coba lagi.');
        header('Location: kelola_data.php');
        exit;
    }

    // --- Delete -----------------------------------------------------
    if ($action === 'hapus') {
        $idMetrik = (int) ($_POST['id_metrik'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM riwayat_metrik_kelulusan WHERE id_metrik = :id');
            $stmt->execute([':id' => $idMetrik]);

            if ($stmt->rowCount() > 0) {
                flash_set('success', 'Baris riwayat berhasil dihapus (nilai SKD & Matematika ikut terhapus otomatis).');
            } else {
                flash_set('error', 'Baris riwayat tidak ditemukan (mungkin sudah dihapus sebelumnya).');
            }
        } catch (PDOException $e) {
            $pesan = 'Gagal menghapus data.';
            if (APP_DEBUG) {
                $pesan .= ' (Detail teknis: ' . $e->getMessage() . ')';
            } else {
                error_log('[kelola_data.php] hapus gagal: ' . $e->getMessage());
            }
            flash_set('error', $pesan);
        }
        header('Location: kelola_data.php');
        exit;
    }

    // --- Create / Update ---------------------------------------------
    if ($action === 'simpan') {
        $idMetrik = (int) ($_POST['id_metrik'] ?? 0);
        $isUpdate = $idMetrik > 0;

        $data = [
            'id_prodi'         => (int) ($_POST['id_prodi'] ?? 0),
            'id_provinsi'      => (int) ($_POST['id_provinsi'] ?? 0),
            'tahun'            => (int) ($_POST['tahun'] ?? 0),
            'jumlah_pendaftar' => (int) ($_POST['jumlah_pendaftar'] ?? 0),
            'kuota'            => (int) ($_POST['kuota'] ?? 0),
            'jumlah_lulus'     => (int) ($_POST['jumlah_lulus'] ?? 0),
            'skd'              => [
                'tertinggi' => (float) ($_POST['skd_tertinggi'] ?? 0),
                'terendah'  => (float) ($_POST['skd_terendah'] ?? 0),
                'rata_rata' => (float) ($_POST['skd_rata_rata'] ?? 0),
            ],
            'matematika'       => [
                'tertinggi' => (float) ($_POST['mtk_tertinggi'] ?? 0),
                'terendah'  => (float) ($_POST['mtk_terendah'] ?? 0),
                'rata_rata' => (float) ($_POST['mtk_rata_rata'] ?? 0),
            ],
        ];

        $errors = validasi_riwayat($data);

        if (!empty($errors)) {
            flash_set('error', implode(' ', $errors));
            // Simpan input yang barusan diketik supaya admin tidak perlu
            // mengisi ulang semua field setelah redirect (Post/Redirect/Get).
            $_SESSION['old_input'] = $_POST;
            $redirect = $isUpdate ? 'kelola_data.php?edit=' . $idMetrik : 'kelola_data.php';
            header('Location: ' . $redirect);
            exit;
        }

        try {
            $pdo->beginTransaction();

            if ($isUpdate) {
                $stmt = $pdo->prepare(
                    'UPDATE riwayat_metrik_kelulusan
                     SET id_prodi = :id_prodi, id_provinsi = :id_provinsi, tahun = :tahun,
                         jumlah_pendaftar = :jumlah_pendaftar, kuota = :kuota, jumlah_lulus = :jumlah_lulus
                     WHERE id_metrik = :id_metrik'
                );
                $stmt->execute([
                    ':id_prodi'         => $data['id_prodi'],
                    ':id_provinsi'      => $data['id_provinsi'],
                    ':tahun'            => $data['tahun'],
                    ':jumlah_pendaftar' => $data['jumlah_pendaftar'],
                    ':kuota'            => $data['kuota'],
                    ':jumlah_lulus'     => $data['jumlah_lulus'],
                    ':id_metrik'        => $idMetrik,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO riwayat_metrik_kelulusan
                        (id_prodi, id_provinsi, tahun, jumlah_pendaftar, kuota, jumlah_lulus)
                     VALUES (:id_prodi, :id_provinsi, :tahun, :jumlah_pendaftar, :kuota, :jumlah_lulus)'
                );
                $stmt->execute([
                    ':id_prodi'         => $data['id_prodi'],
                    ':id_provinsi'      => $data['id_provinsi'],
                    ':tahun'            => $data['tahun'],
                    ':jumlah_pendaftar' => $data['jumlah_pendaftar'],
                    ':kuota'            => $data['kuota'],
                    ':jumlah_lulus'     => $data['jumlah_lulus'],
                ]);
                $idMetrik = (int) $pdo->lastInsertId();
            }

            // ON DUPLICATE KEY UPDATE: insert baris nilai kalau belum ada
            // (mis. tambah data baru), atau perbarui kalau sudah ada (edit).
            // Berlaku untuk kedua kasus dengan satu query yang sama.
            $nilaiStmt = $pdo->prepare(
                'INSERT INTO riwayat_nilai (id_metrik, jenis_ujian, nilai_tertinggi, nilai_terendah, nilai_rata_rata)
                 VALUES (:id_metrik, :jenis_ujian, :tertinggi, :terendah, :rata_rata)
                 ON DUPLICATE KEY UPDATE
                     nilai_tertinggi = VALUES(nilai_tertinggi),
                     nilai_terendah  = VALUES(nilai_terendah),
                     nilai_rata_rata = VALUES(nilai_rata_rata)'
            );
            foreach (['SKD' => 'skd', 'Matematika' => 'matematika'] as $jenisUjian => $key) {
                $nilaiStmt->execute([
                    ':id_metrik'  => $idMetrik,
                    ':jenis_ujian' => $jenisUjian,
                    ':tertinggi'  => $data[$key]['tertinggi'],
                    ':terendah'   => $data[$key]['terendah'],
                    ':rata_rata'  => $data[$key]['rata_rata'],
                ]);
            }

            $pdo->commit();
            unset($_SESSION['old_input']);
            flash_set('success', $isUpdate ? 'Data riwayat berhasil diperbarui.' : 'Data riwayat baru berhasil ditambahkan.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $driverCode = $e->errorInfo[1] ?? null;
            if ($driverCode === 1062) {
                $pesan = 'Kombinasi tahun, program studi, dan provinsi ini sudah ada. Silakan edit baris yang sudah ada, bukan menambah baris baru.';
            } else {
                $pesan = 'Gagal menyimpan data ke database.';
            }
            if (APP_DEBUG) {
                $pesan .= ' (Detail teknis: ' . $e->getMessage() . ')';
            } else {
                error_log('[kelola_data.php] simpan gagal: ' . $e->getMessage());
            }
            flash_set('error', $pesan);
        }

        header('Location: kelola_data.php');
        exit;
    }
}

// -----------------------------------------------------------------
// 3. SIAPKAN DATA UNTUK RENDER HALAMAN (GET)
// -----------------------------------------------------------------
$prodiList    = $pdo->query('SELECT id_prodi, kode_prodi, nama_prodi, jenjang FROM program_studi ORDER BY jenjang, nama_prodi')->fetchAll();
$provinsiList = $pdo->query('SELECT id_provinsi, kode_provinsi, nama_provinsi FROM provinsi ORDER BY kode_provinsi')->fetchAll();

$formValues = [
    'id_metrik' => 0, 'id_prodi' => '', 'id_provinsi' => '', 'tahun' => '',
    'jumlah_pendaftar' => '', 'kuota' => '', 'jumlah_lulus' => '',
    'skd_tertinggi' => '', 'skd_terendah' => '', 'skd_rata_rata' => '',
    'mtk_tertinggi' => '', 'mtk_terendah' => '', 'mtk_rata_rata' => '',
];
$formMode  = 'create';
$editId    = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if (!empty($_SESSION['old_input'])) {
    // Validasi form terakhir gagal -- isi ulang dengan input yang barusan diketik admin.
    $old = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
    foreach (array_keys($formValues) as $key) {
        if (isset($old[$key])) {
            $formValues[$key] = $old[$key];
        }
    }
    $formValues['id_metrik'] = (int) ($old['id_metrik'] ?? 0);
    $formMode = $formValues['id_metrik'] > 0 ? 'edit' : 'create';
} elseif ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT m.id_metrik, m.id_prodi, m.id_provinsi, m.tahun, m.jumlah_pendaftar, m.kuota, m.jumlah_lulus,
                skd.nilai_tertinggi AS skd_tertinggi, skd.nilai_terendah AS skd_terendah, skd.nilai_rata_rata AS skd_rata_rata,
                mtk.nilai_tertinggi AS mtk_tertinggi, mtk.nilai_terendah AS mtk_terendah, mtk.nilai_rata_rata AS mtk_rata_rata
         FROM riwayat_metrik_kelulusan m
         LEFT JOIN riwayat_nilai skd ON skd.id_metrik = m.id_metrik AND skd.jenis_ujian = \'SKD\'
         LEFT JOIN riwayat_nilai mtk ON mtk.id_metrik = m.id_metrik AND mtk.jenis_ujian = \'Matematika\'
         WHERE m.id_metrik = :id'
    );
    $stmt->execute([':id' => $editId]);
    $row = $stmt->fetch();

    if ($row) {
        foreach ($formValues as $key => $default) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $formValues[$key] = $row[$key];
            }
        }
        $formMode = 'edit';
    } else {
        flash_set('error', 'Baris riwayat yang ingin diedit tidak ditemukan (mungkin sudah dihapus).');
    }
}

// Filter tabel (opsional): tahun & prodi, dikirim lewat GET supaya bisa di-bookmark.
$filterTahun = isset($_GET['f_tahun']) && $_GET['f_tahun'] !== '' ? (int) $_GET['f_tahun'] : null;
$filterProdi = isset($_GET['f_prodi']) && $_GET['f_prodi'] !== '' ? (int) $_GET['f_prodi'] : null;

$where  = [];
$params = [];
if ($filterTahun !== null) {
    $where[]              = 'm.tahun = :f_tahun';
    $params[':f_tahun']    = $filterTahun;
}
if ($filterProdi !== null) {
    $where[]              = 'm.id_prodi = :f_prodi';
    $params[':f_prodi']    = $filterProdi;
}
// $whereSql hanya menyusun BENTUK klausa WHERE dari daftar tetap di atas
// (bukan dari input mentah pengguna) -- nilai filter tetap dikirim lewat
// parameter terikat ($params), jadi tetap aman dari SQL Injection.
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$listStmt = $pdo->prepare(
    "SELECT m.id_metrik, m.tahun, p.kode_prodi, p.nama_prodi, v.kode_provinsi, v.nama_provinsi,
            m.jumlah_pendaftar, m.kuota, m.jumlah_lulus, m.persentase_lolos,
            skd.nilai_rata_rata AS skd_rata_rata, mtk.nilai_rata_rata AS mtk_rata_rata
     FROM riwayat_metrik_kelulusan m
     JOIN program_studi p ON p.id_prodi = m.id_prodi
     JOIN provinsi v ON v.id_provinsi = m.id_provinsi
     LEFT JOIN riwayat_nilai skd ON skd.id_metrik = m.id_metrik AND skd.jenis_ujian = 'SKD'
     LEFT JOIN riwayat_nilai mtk ON mtk.id_metrik = m.id_metrik AND mtk.jenis_ujian = 'Matematika'
     $whereSql
     ORDER BY m.tahun DESC, p.kode_prodi, v.kode_provinsi"
);
$listStmt->execute($params);
$riwayatRows = $listStmt->fetchAll();

$tahunOptions = $pdo->query('SELECT DISTINCT tahun FROM riwayat_metrik_kelulusan ORDER BY tahun DESC')->fetchAll(PDO::FETCH_COLUMN);

$flash     = flash_get();
$csrfToken = csrf_token();

/** Helper singkat supaya template HTML di bawah tidak dipenuhi htmlspecialchars() berulang. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Tema warna per program studi buat tabel admin -- biar barisnya nggak
 * monoton abu-abu/putih doang, dan admin bisa langsung nebak prodi dari
 * warna garis kiri + chip-nya (sama seperti tema warna kartu di dashboard).
 */
function prodi_theme(string $kodeProdi): array
{
    $themes = [
        'DIII-ST' => ['border' => 'border-l-blue-500',   'chip' => 'bg-blue-50 text-blue-700'],
        'DIV-ST'  => ['border' => 'border-l-violet-500', 'chip' => 'bg-violet-50 text-violet-700'],
        'DIV-KS'  => ['border' => 'border-l-amber-500',  'chip' => 'bg-amber-50 text-amber-700'],
    ];
    return $themes[$kodeProdi] ?? ['border' => 'border-l-slate-300', 'chip' => 'bg-slate-100 text-slate-700'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PantauSTIS — Kelola Data</title>
  <meta name="description" content="Panel admin: tambah, ubah, dan hapus data riwayat SPMB STIS.">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    /* Font khusus HANYA untuk logo (PantauSTIS / Tracker SPMB STIS). Font lain tetap default. */
    if (window.tailwind) {
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              brand: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui', 'sans-serif']
            }
          }
        }
      };
    }
  </script>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">

  <!-- ======================= HEADER ======================= -->
  <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-900">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
      <a href="dashboard.html" class="flex items-center gap-2.5">
        <span class="flex h-11 w-11 items-center justify-center rounded-lg p-1 shadow-sm ring-1 ring-slate-800">
          <img src="assets/img/logo PantauSTIS.png" alt="Logo PantauSTIS" class="h-full w-full object-contain">
        </span>
        <span class="leading-tight">
          <span class="font-brand block text-sm font-semibold text-white">PantauSTIS</span>
          <span class="font-brand block text-[11px] text-slate-400">Tracker SPMB STIS</span>
        </span>
      </a>
      <nav class="hidden items-center gap-6 text-sm font-medium sm:flex">
        <a href="dashboard.html" class="pb-[18px] pt-[18px] text-slate-300 hover:text-white">Dashboard</a>
        <a href="bandingkan.html" class="pb-[18px] pt-[18px] text-slate-300 hover:text-white">Bandingkan</a>
        <span class="border-b-2 border-blue-500 pb-[18px] pt-[18px] text-white">Kelola Data</span>
      </nav>
      <div class="flex items-center gap-3">
        <span class="hidden text-xs text-slate-400 sm:inline"> <span class="font-medium text-slate-200"><?= h($_SESSION['admin_nama']) ?></span></span>
        <a href="logout.php" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-800">Logout</a>
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

    <div class="mb-6">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Kelola Data Riwayat SPMB</h1>
      <p class="mt-1 text-sm text-slate-500">Tambah data riwayat tahun baru, perbaiki kuota/pendaftar yang salah, atau hapus baris yang tidak relevan.</p>
    </div>

    <?php if ($flash): ?>
    <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
      <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- ======================= FORM TAMBAH / EDIT ======================= -->
    <div id="formRiwayat" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="mb-5 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-800">
          <?= $formMode === 'edit' ? 'Edit Baris Riwayat #' . h($formValues['id_metrik']) : 'Tambah Data Riwayat Tahun Baru' ?>
        </h2>
        <?php if ($formMode === 'edit'): ?>
        <a href="kelola_data.php" class="text-sm font-medium text-slate-500 hover:text-slate-700">Batal edit &times;</a>
        <?php endif; ?>
      </div>

      <form method="post" action="kelola_data.php" class="space-y-6" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="simpan">
        <input type="hidden" name="id_metrik" value="<?= h($formValues['id_metrik']) ?>">

        <!-- Data pokok -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label for="id_prodi" class="mb-1.5 block text-sm font-medium text-slate-700">Program Studi</label>
            <select id="id_prodi" name="id_prodi" required
              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              <option value="">Pilih prodi&hellip;</option>
              <?php foreach ($prodiList as $prodi): ?>
              <option value="<?= h($prodi['id_prodi']) ?>" <?= (string) $formValues['id_prodi'] === (string) $prodi['id_prodi'] ? 'selected' : '' ?>>
                <?= h($prodi['nama_prodi']) ?> (<?= h($prodi['jenjang']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="id_provinsi" class="mb-1.5 block text-sm font-medium text-slate-700">Provinsi</label>
            <select id="id_provinsi" name="id_provinsi" required
              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              <option value="">Pilih provinsi&hellip;</option>
              <?php foreach ($provinsiList as $provinsi): ?>
              <option value="<?= h($provinsi['id_provinsi']) ?>" <?= (string) $formValues['id_provinsi'] === (string) $provinsi['id_provinsi'] ? 'selected' : '' ?>>
                <?= h($provinsi['kode_provinsi']) ?> &middot; <?= h($provinsi['nama_provinsi']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="tahun" class="mb-1.5 block text-sm font-medium text-slate-700">Tahun</label>
            <input type="number" id="tahun" name="tahun" min="2000" max="2100" required
              value="<?= h($formValues['tahun']) ?>" placeholder="cth. 2026"
              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
          </div>

          <div>
            <label for="jumlah_pendaftar" class="mb-1.5 block text-sm font-medium text-slate-700">Jumlah Pendaftar</label>
            <input type="number" id="jumlah_pendaftar" name="jumlah_pendaftar" min="0" required
              value="<?= h($formValues['jumlah_pendaftar']) ?>"
              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
          </div>

          <div>
            <label for="kuota" class="mb-1.5 block text-sm font-medium text-slate-700">Kuota</label>
            <input type="number" id="kuota" name="kuota" min="0" required
              value="<?= h($formValues['kuota']) ?>"
              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
          </div>

          <div>
            <label for="jumlah_lulus" class="mb-1.5 block text-sm font-medium text-slate-700">Jumlah Lulus</label>
            <input type="number" id="jumlah_lulus" name="jumlah_lulus" min="0" required
              value="<?= h($formValues['jumlah_lulus']) ?>"
              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
          </div>
        </div>

        <!-- Nilai SKD & Matematika -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-1.5 text-sm font-semibold text-slate-700">Nilai SKD <span class="font-normal text-slate-400">(skala 0&ndash;550)</span></legend>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label for="skd_tertinggi" class="mb-1 block text-xs text-slate-500">Tertinggi</label>
                <input type="number" step="0.01" min="0" max="550" id="skd_tertinggi" name="skd_tertinggi" required
                  value="<?= h($formValues['skd_tertinggi']) ?>"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              </div>
              <div>
                <label for="skd_rata_rata" class="mb-1 block text-xs text-slate-500">Rata-rata</label>
                <input type="number" step="0.01" min="0" max="550" id="skd_rata_rata" name="skd_rata_rata" required
                  value="<?= h($formValues['skd_rata_rata']) ?>"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              </div>
              <div>
                <label for="skd_terendah" class="mb-1 block text-xs text-slate-500">Terendah</label>
                <input type="number" step="0.01" min="0" max="550" id="skd_terendah" name="skd_terendah" required
                  value="<?= h($formValues['skd_terendah']) ?>"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              </div>
            </div>
          </fieldset>

          <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-1.5 text-sm font-semibold text-slate-700">Nilai Matematika <span class="font-normal text-slate-400">(skala 0&ndash;200)</span></legend>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label for="mtk_tertinggi" class="mb-1 block text-xs text-slate-500">Tertinggi</label>
                <input type="number" step="0.01" min="0" max="200" id="mtk_tertinggi" name="mtk_tertinggi" required
                  value="<?= h($formValues['mtk_tertinggi']) ?>"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              </div>
              <div>
                <label for="mtk_rata_rata" class="mb-1 block text-xs text-slate-500">Rata-rata</label>
                <input type="number" step="0.01" min="0" max="200" id="mtk_rata_rata" name="mtk_rata_rata" required
                  value="<?= h($formValues['mtk_rata_rata']) ?>"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              </div>
              <div>
                <label for="mtk_terendah" class="mb-1 block text-xs text-slate-500">Terendah</label>
                <input type="number" step="0.01" min="0" max="200" id="mtk_terendah" name="mtk_terendah" required
                  value="<?= h($formValues['mtk_terendah']) ?>"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              </div>
            </div>
          </fieldset>
        </div>

        <div class="flex items-center gap-3">
          <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
            <?= $formMode === 'edit' ? 'Simpan Perubahan' : 'Tambah Data' ?>
          </button>
          <?php if ($formMode === 'edit'): ?>
          <a href="kelola_data.php" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- ======================= FILTER + TABEL DAFTAR RIWAYAT ======================= -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-sm font-semibold text-slate-700">Seluruh Data Riwayat (<?= count($riwayatRows) ?> baris)</h2>
        <form method="get" action="kelola_data.php" class="flex flex-wrap gap-2">
          <select name="f_tahun" onchange="this.form.submit()"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
            <option value="">Semua Tahun</option>
            <?php foreach ($tahunOptions as $tahun): ?>
            <option value="<?= h($tahun) ?>" <?= $filterTahun === (int) $tahun ? 'selected' : '' ?>><?= h($tahun) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="f_prodi" onchange="this.form.submit()"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
            <option value="">Semua Prodi</option>
            <?php foreach ($prodiList as $prodi): ?>
            <option value="<?= h($prodi['id_prodi']) ?>" <?= $filterProdi === (int) $prodi['id_prodi'] ? 'selected' : '' ?>><?= h($prodi['kode_prodi']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($filterTahun !== null || $filterProdi !== null): ?>
          <a href="kelola_data.php" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Reset</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
          <thead class="bg-slate-50">
            <tr>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prodi</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Provinsi</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Pendaftar</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Kuota</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Lulus</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">% Lolos</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Rata SKD</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Rata Mtk</th>
              <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php if (empty($riwayatRows)): ?>
            <tr><td colspan="10" class="px-4 py-10 text-center text-sm text-slate-400">Belum ada data riwayat yang cocok dengan filter ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($riwayatRows as $i => $row): $theme = prodi_theme($row['kode_prodi']); ?>
            <tr class="border-l-4 <?= $theme['border'] ?> <?= $i % 2 === 0 ? 'bg-white' : 'bg-slate-50/60' ?> hover:bg-blue-50/40">
              <td class="px-4 py-3 text-sm font-medium text-slate-800"><?= h($row['tahun']) ?></td>
              <td class="px-4 py-3 text-sm">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $theme['chip'] ?>"><?= h($row['kode_prodi']) ?></span>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700"><?= h($row['kode_provinsi']) ?> &middot; <?= h($row['nama_provinsi']) ?></td>
              <td class="px-4 py-3 text-right text-sm text-slate-700"><?= h(number_format((float) $row['jumlah_pendaftar'], 0, ',', '.')) ?></td>
              <td class="px-4 py-3 text-right text-sm text-slate-700"><?= h(number_format((float) $row['kuota'], 0, ',', '.')) ?></td>
              <td class="px-4 py-3 text-right text-sm text-slate-700"><?= h(number_format((float) $row['jumlah_lulus'], 0, ',', '.')) ?></td>
              <td class="px-4 py-3 text-right text-sm">
                <?php $persen = (float) $row['persentase_lolos']; ?>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $persen >= 3 ? 'bg-emerald-50 text-emerald-700' : ($persen >= 1.5 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') ?>">
                  <?= h(number_format($persen, 2, ',', '.')) ?>%
                </span>
              </td>
              <td class="px-4 py-3 text-right text-sm text-slate-600"><?= $row['skd_rata_rata'] !== null ? h(number_format((float) $row['skd_rata_rata'], 2, ',', '.')) : '<span class="italic text-slate-400">&mdash;</span>' ?></td>
              <td class="px-4 py-3 text-right text-sm text-slate-600"><?= $row['mtk_rata_rata'] !== null ? h(number_format((float) $row['mtk_rata_rata'], 2, ',', '.')) : '<span class="italic text-slate-400">&mdash;</span>' ?></td>
              <td class="px-4 py-3 text-center text-sm">
                <div class="flex items-center justify-center gap-3">
                  <a href="kelola_data.php?edit=<?= h($row['id_metrik']) ?>#formRiwayat" class="font-medium text-blue-600 hover:underline">Edit</a>
                  <form method="post" action="kelola_data.php" data-confirm-hapus
                    data-confirm-message="Hapus riwayat <?= h($row['kode_prodi']) ?> &middot; <?= h($row['nama_provinsi']) ?> tahun <?= h($row['tahun']) ?>? Nilai SKD &amp; Matematika terkait ikut terhapus.">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_metrik" value="<?= h($row['id_metrik']) ?>">
                    <button type="submit" class="font-medium text-red-600 hover:underline">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <footer class="mt-12 border-t border-slate-200 py-6 text-center text-xs text-slate-400">
    PantauSTIS &middot; Tracker SPMB STIS &middot; Data diambil dari laman resmi <a href="https://spmb.stis.ac.id" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">spmb.stis.ac.id</a>.
  </footer>

  <script src="assets/js/kelola.js"></script>
</body>
</html>
