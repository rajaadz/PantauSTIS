<?php

declare(strict_types=1);

/**
 * login.php
 * ---------------------------------------------------------------------
 * Halaman login admin. Validasi dilakukan ke tabel `admin` lewat PDO
 * prepared statement (aman dari SQL Injection) + password_verify()
 * terhadap password_hash yang tersimpan (tidak pernah menyimpan/
 * membandingkan teks password asli).
 *
 * Kredensial seed bawaan (lihat tracker_spmb_stis.sql bagian 4.1):
 *   username : admin
 *   password : Admin#2026
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/koneksi.php';

// Sudah login -> langsung ke halaman kelola data, tidak perlu login ulang.
if (is_logged_in()) {
    header('Location: kelola_data.php');
    exit;
}

$errorMessage = '';
$oldUsername  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldUsername = trim((string) ($_POST['username'] ?? ''));
    $password    = (string) ($_POST['password'] ?? '');
    $csrf        = (string) ($_POST['csrf_token'] ?? '');

    if (!csrf_verify($csrf)) {
        $errorMessage = 'Sesi form sudah kedaluwarsa. Silakan coba lagi.';
    } elseif ($oldUsername === '' || $password === '') {
        $errorMessage = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id_admin, username, password_hash, nama_lengkap, is_aktif
             FROM admin WHERE username = :username LIMIT 1'
        );
        $stmt->execute([':username' => $oldUsername]);
        $admin = $stmt->fetch();

        $valid = $admin
            && (int) $admin['is_aktif'] === 1
            && password_verify($password, $admin['password_hash']);

        if ($valid) {
            // Cegah session fixation: buat ID sesi baru setelah login berhasil.
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int) $admin['id_admin'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_nama']     = $admin['nama_lengkap'];

            $update = $pdo->prepare('UPDATE admin SET last_login = NOW() WHERE id_admin = :id');
            $update->execute([':id' => $admin['id_admin']]);

            flash_set('success', 'Berhasil masuk sebagai ' . $admin['nama_lengkap'] . '.');
            header('Location: kelola_data.php');
            exit;
        }

        $errorMessage = 'Username atau password salah.';
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PantauSTIS — Login Admin</title>
  <meta name="description" content="Login admin untuk mengelola data riwayat SPMB STIS.">
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
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased">

  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center px-4 py-3 sm:px-6 lg:px-8">
      <a href="index.html" class="flex items-center gap-2.5">
        <span class="flex h-11 w-11 items-center justify-center rounded-lg p-1 shadow-sm ring-1 ring-slate-800">
          <img src="assets/img/logo PantauSTIS.png" alt="Logo PantauSTIS" class="h-full w-full object-contain">
        </span>
        <span class="leading-tight">
          <span class="font-brand block text-sm font-semibold text-slate-900">PantauSTIS</span>
          <span class="font-brand block text-[11px] text-slate-500">Tracker SPMB STIS</span>
        </span>
      </a>
    </div>
  </header>

  <main class="flex flex-1 items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">

      <div class="mb-6 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white shadow-sm">PS</span>
        <h1 class="mt-4 text-xl font-semibold tracking-tight text-slate-900">Login Admin</h1>
        <p class="mt-1 text-sm text-slate-500">Masuk untuk mengelola data riwayat SPMB STIS.</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        <?php if ($errorMessage !== ''): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
          <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <form method="post" action="login.php" class="space-y-4" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <div>
            <label for="username" class="mb-1.5 block text-sm font-medium text-slate-700">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username" autofocus
              value="<?= htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8') ?>"
              class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              placeholder="admin">
          </div>

          <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
              class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
          </div>

          <button type="submit"
            class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
            Masuk
          </button>
        </form>
      </div>

      <p class="mt-5 text-center text-sm text-slate-500">
        Bukan admin? <a href="dashboard.html" class="font-medium text-blue-600 hover:underline">Kembali ke Dashboard</a>
      </p>
    </div>
  </main>

  <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-400">
    PantauSTIS &middot; Tracker SPMB STIS &middot; Data diambil dari laman resmi <a href="https://spmb.stis.ac.id" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">spmb.stis.ac.id</a>.
  </footer>
</body>
</html>
