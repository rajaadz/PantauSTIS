/* jshint esversion: 11, browser: true, devel: true, undef: true, unused: true, curly: true, eqeqeq: true */
/* globals confirm */

/**
 * kelola.js
 * ---------------------------------------------------------------------
 * Skrip kecil khusus kelola_data.php: minta konfirmasi sebelum submit
 * form hapus, supaya baris riwayat (dan nilai SKD/Matematika terkait)
 * tidak terhapus karena klik tidak sengaja. Logika CRUD sesungguhnya
 * (Create/Update/Delete) ada di PHP -- file ini murni pengalaman
 * pengguna di sisi klien.
 * ---------------------------------------------------------------------
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm-hapus]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        const message = form.getAttribute('data-confirm-message') || 'Yakin ingin menghapus baris ini?';
        if (!confirm(message)) {
          event.preventDefault();
        }
      });
    });
  });

}());
