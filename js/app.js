/* jshint esversion: 11, browser: true, devel: true, undef: true, unused: true, curly: true, eqeqeq: true */
/* globals fetch, LiveSearch, Chart */

/**
 * app.js
 * ---------------------------------------------------------------------
 * Logika halaman untuk Tracker SPMB STIS (PantauSTIS): mengambil data
 * (mock JSON untuk sekarang, endpoint PHP nanti), merender kartu/tabel,
 * dan menyalakan live-search lewat komponen di live-search.js.
 *
 * Setiap halaman ditandai lewat atribut <body data-page="..."> supaya
 * app.js tahu bagian mana yang harus dijalankan:
 *   - data-page="dashboard"   -> dashboard.html
 *   - data-page="pesaing"     -> pesaing.html
 *   - data-page="detail"      -> detail.html
 *   - data-page="bandingkan"  -> bandingkan.html
 * (index.html adalah halaman gerbang/landing statis, tidak punya
 * data-page -- app.js hanya dipakai di situ untuk initHeaderAuthState().)
 *
 * CONFIG.useMockData = true  -> baca dari assets/data/*.json (demo, tanpa PHP)
 * CONFIG.useMockData = false -> baca dari endpoint PHP asli (lihat README.md)
 *
 * Status login admin (initHeaderAuthState) SELALU dicek lewat
 * api/session_status.php, terlepas dari CONFIG.useMockData -- sesi PHP
 * memang tidak bisa di-mock di sisi klien. Kalau backend PHP belum
 * disambungkan, permintaan itu gagal secara diam-diam dan header tetap
 * menampilkan tombol "Login Admin" bawaan.
 * ---------------------------------------------------------------------
 */
(function () {
  'use strict';

  const CONFIG = {
    useMockData: false,
    apiBase: 'api/',
    mockBase: 'assets/data/'
  };

  /* ------------------------------------------------------------- *
   * Util umum
   * ------------------------------------------------------------- */

  function fetchJson(url) {
    return fetch(url).then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.json();
    });
  }

  function formatNumber(value) {
    return new Intl.NumberFormat('id-ID').format(value);
  }

  function formatDecimal(value, digits) {
    return new Intl.NumberFormat('id-ID', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    }).format(value);
  }

  function computePersentaseLolos(row) {
    if (!row.jumlah_pendaftar) {
      return 0;
    }
    return (row.jumlah_lulus / row.jumlah_pendaftar) * 100;
  }

  function getQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  function flashElement(el) {
    if (!el) {
      return;
    }
    el.classList.remove('flash-highlight');
    /* force reflow supaya animasi bisa diulang walau class sama */
    void el.offsetWidth;
    el.classList.add('flash-highlight');
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Kelas warna badge % Lolos -- dipakai di Tabel Pesaing & Bandingkan
   * supaya baris dengan tingkat kelulusan longgar/sedang/ketat langsung
   * kebaca dari warna, bukan cuma dari angka. Ambang batas (3% / 1.5%)
   * sengaja tidak terlalu presisi karena datanya memang dummy -- yang
   * penting variasi tiga warnanya konsisten dengan kelola_data.php.
   */
  function persenBadgeClass(persen) {
    if (persen >= 3) {
      return 'bg-emerald-50 text-emerald-700';
    }
    if (persen >= 1.5) {
      return 'bg-amber-50 text-amber-700';
    }
    return 'bg-red-50 text-red-700';
  }

  /**
   * Tema warna per program studi -- dipakai di kartu Dashboard, chip
   * provinsi di Tabel Pesaing, dan grafik Bandingkan, supaya tiap prodi
   * konsisten punya warna sendiri (bukan cuma biru/abu-abu monoton).
   * Urutan warnanya sengaja dibedain jauh (biru / ungu / oranye) biar
   * gampang dibedain sekilas.
   */
  function prodiColorTheme(kodeProdi) {
    const themes = {
      'DIII-ST': {
        bar: 'bg-blue-500',
        badge: 'bg-blue-50 text-blue-700',
        icon: 'bg-blue-50 text-blue-600',
        button: 'bg-blue-600 hover:bg-blue-700',
        dot: 'bg-blue-500'
      },
      'DIV-ST': {
        bar: 'bg-violet-500',
        badge: 'bg-violet-50 text-violet-700',
        icon: 'bg-violet-50 text-violet-600',
        button: 'bg-violet-600 hover:bg-violet-700',
        dot: 'bg-violet-500'
      },
      'DIV-KS': {
        bar: 'bg-amber-500',
        badge: 'bg-amber-50 text-amber-700',
        icon: 'bg-amber-50 text-amber-600',
        button: 'bg-amber-600 hover:bg-amber-700',
        dot: 'bg-amber-500'
      }
    };
    return themes[kodeProdi] || {
      bar: 'bg-slate-400',
      badge: 'bg-slate-100 text-slate-700',
      icon: 'bg-slate-100 text-slate-600',
      button: 'bg-slate-600 hover:bg-slate-700',
      dot: 'bg-slate-400'
    };
  }

  /**
   * Status login admin di header (#authSlot), dipakai di SEMUA halaman
   * (termasuk index.html yang tidak punya data-page). Kalau elemennya
   * tidak ada di halaman ini, keluar diam-diam.
   */
  function initHeaderAuthState() {
    const slot = document.querySelector('#authSlot');
    if (!slot) {
      return;
    }

    fetchJson('api/session_status.php')
      .then(function (json) {
        if (!json.loggedIn) {
          return;
        }
        slot.innerHTML = '' +
          '<span class="hidden text-slate-400 sm:inline">Masuk sebagai <span class="font-medium text-slate-200">' + escapeHtml(json.namaLengkap || '') + '</span></span>' +
          '<a href="kelola_data.php" class="rounded-lg bg-blue-600 px-3 py-1.5 font-medium text-white transition hover:bg-blue-700">Kelola Data</a>' +
          '<a href="logout.php" class="rounded-lg border border-slate-700 px-3 py-1.5 font-medium text-slate-300 transition hover:bg-slate-800">Logout</a>';
      })
      .catch(function () {
        /* Backend PHP belum disambungkan / gagal diakses -- biarkan tombol "Login Admin" bawaan. */
      });
  }

  /* ------------------------------------------------------------- *
   * Lapisan data (mock JSON sekarang, endpoint PHP nanti)
   * ------------------------------------------------------------- */

  function getProdiList() {
    const url = CONFIG.useMockData ? CONFIG.mockBase + 'prodi.json' : CONFIG.apiBase + 'prodi.php';
    return fetchJson(url).then(function (json) {
      return CONFIG.useMockData ? json : json.data;
    });
  }

  function getProvinsiList() {
    const url = CONFIG.useMockData ? CONFIG.mockBase + 'provinsi.json' : CONFIG.apiBase + 'provinsi.php';
    return fetchJson(url).then(function (json) {
      return CONFIG.useMockData ? json : json.data;
    });
  }

  function getRiwayatList() {
    const url = CONFIG.useMockData ? CONFIG.mockBase + 'riwayat.json' : CONFIG.apiBase + 'riwayat.php';
    return fetchJson(url).then(function (json) {
      return CONFIG.useMockData ? json : json.data;
    });
  }

  /* ------------------------------------------------------------- *
   * Pencarian Prodi & Provinsi (live-search) -- dipakai di lebih
   * dari satu halaman, jadi ditulis sebagai fungsi bersama.
   * ------------------------------------------------------------- */

  function buildProdiUrl(query) {
    return CONFIG.useMockData ?
      CONFIG.mockBase + 'prodi.json' :
      CONFIG.apiBase + 'cari_prodi.php?q=' + encodeURIComponent(query);
  }

  function mapProdiResponse(json, query) {
    const list = CONFIG.useMockData ? json : (json.data || []);
    const q = query.toLowerCase();
    return list.filter(function (item) {
      return item.nama_prodi.toLowerCase().indexOf(q) !== -1 ||
        item.kode_prodi.toLowerCase().indexOf(q) !== -1;
    });
  }

  function buildProvinsiUrl(query) {
    return CONFIG.useMockData ?
      CONFIG.mockBase + 'provinsi.json' :
      CONFIG.apiBase + 'cari_provinsi.php?q=' + encodeURIComponent(query);
  }

  function mapProvinsiResponse(json, query) {
    const list = CONFIG.useMockData ? json : (json.data || []);
    const q = query.toLowerCase();
    return list.filter(function (item) {
      return item.nama_provinsi.toLowerCase().indexOf(q) !== -1;
    });
  }

  /* ------------------------------------------------------------- *
   * Halaman: Dashboard Utama (index.html)
   * ------------------------------------------------------------- */

  function initDashboardPage() {
    const grid = document.querySelector('#prodiGrid');
    const emptyState = document.querySelector('#prodiEmptyState');
    const tahunFilter = document.querySelector('#tahunFilter');
    const jenjangFilter = document.querySelector('#jenjangFilter');
    const resetBtn = document.querySelector('#resetFilter');
    const cariProdiInput = document.querySelector('#cariProdi');
    const cariProdiPanel = document.querySelector('#cariProdiPanel');

    if (!grid) {
      return;
    }

    let prodiList = [];

    function cardTemplate(prodi) {
      const theme = prodiColorTheme(prodi.kode_prodi);

      return '' +
        '<article data-kode-prodi="' + prodi.kode_prodi + '" data-jenjang="' + prodi.jenjang + '" ' +
        'class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">' +
        '<div class="h-1.5 ' + theme.bar + '"></div>' +
        '<div class="p-6">' +
        '<div class="flex items-center justify-between">' +
        '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' + theme.badge + '">' + prodi.jenjang + '</span>' +
        '<span class="flex h-9 w-9 items-center justify-center rounded-lg ' + theme.icon + '">' +
        '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0z"/></svg>' +
        '</span>' +
        '</div>' +
        '<h3 class="mt-3 text-lg font-semibold text-slate-900">' + prodi.nama_prodi + '</h3>' +
        '<p class="mt-0.5 font-mono text-xs text-slate-400">' + prodi.kode_prodi + '</p>' +
        '<p class="prodi-deskripsi mt-3 hidden text-sm leading-relaxed text-slate-600">' + prodi.deskripsi + '</p>' +
        '<div class="mt-5 flex gap-2">' +
        '<button type="button" data-action="pesaing" class="inline-flex flex-1 items-center justify-center rounded-lg px-3 py-2 text-sm font-medium text-white transition ' + theme.button + '">Lihat Pesaing</button>' +
        '<button type="button" data-action="deskripsi" class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Deskripsi</button>' +
        '</div>' +
        '</div>' +
        '</article>';
    }

    function renderCards(list) {
      grid.innerHTML = list.map(cardTemplate).join('');

      grid.querySelectorAll('[data-action="deskripsi"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const desc = btn.closest('article').querySelector('.prodi-deskripsi');
          desc.classList.toggle('hidden');
        });
      });

      grid.querySelectorAll('[data-action="pesaing"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const kodeProdi = btn.closest('article').getAttribute('data-kode-prodi');
          const tahun = tahunFilter && tahunFilter.value ? tahunFilter.value : '';
          window.location.href = 'pesaing.html?prodi=' + encodeURIComponent(kodeProdi) +
            (tahun ? '&tahun=' + encodeURIComponent(tahun) : '');
        });
      });
    }

    function applyFilters() {
      const jenjang = jenjangFilter ? jenjangFilter.value : '';
      const cards = grid.querySelectorAll('[data-kode-prodi]');
      let visibleCount = 0;

      cards.forEach(function (card) {
        const match = !jenjang || card.getAttribute('data-jenjang') === jenjang;
        card.classList.toggle('hidden', !match);
        if (match) {
          visibleCount += 1;
        }
      });

      if (emptyState) {
        emptyState.classList.toggle('hidden', visibleCount > 0);
      }
    }

    function populateTahunFilter(riwayatList) {
      if (!tahunFilter) {
        return;
      }
      const years = Array.from(new Set(riwayatList.map(function (row) { return row.tahun; })))
        .sort(function (a, b) { return b - a; });

      years.forEach(function (year) {
        const option = document.createElement('option');
        option.value = String(year);
        option.textContent = String(year);
        tahunFilter.appendChild(option);
      });

      if (years.length > 0) {
        tahunFilter.value = String(years[0]);
      }
    }

    Promise.all([getProdiList(), getRiwayatList()])
      .then(function (results) {
        prodiList = results[0];
        renderCards(prodiList);
        populateTahunFilter(results[1]);
      })
      .catch(function () {
        grid.innerHTML = '<p class="col-span-full text-sm text-red-600">Gagal memuat data program studi.</p>';
      });

    if (jenjangFilter) {
      jenjangFilter.addEventListener('change', applyFilters);
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (jenjangFilter) {
          jenjangFilter.value = '';
        }
        if (cariProdiInput) {
          cariProdiInput.value = '';
        }
        applyFilters();
      });
    }

    if (cariProdiInput && cariProdiPanel) {
      LiveSearch.create({
        inputEl: cariProdiInput,
        panelEl: cariProdiPanel,
        buildUrl: buildProdiUrl,
        mapResponse: mapProdiResponse,
        renderLabel: function (item) { return item.nama_prodi; },
        renderMeta: function (item) { return item.jenjang; },
        emptyText: 'Program studi tidak ditemukan.',
        onSelect: function (item) {
          const card = grid.querySelector('[data-kode-prodi="' + item.kode_prodi + '"]');
          flashElement(card);
        }
      });
    }
  }

  /* ------------------------------------------------------------- *
   * Halaman: Tabel Pesaing (pesaing.html)
   * ------------------------------------------------------------- */

  function initPesaingPage() {
    const tableBody = document.querySelector('#pesaingTableBody');
    const emptyState = document.querySelector('#pesaingEmptyState');
    const notFoundNotice = document.querySelector('#provinsiNotFoundNotice');

    if (!tableBody) {
      return;
    }

    const heading = document.querySelector('#pageHeading');
    const breadcrumb = document.querySelector('#breadcrumbProdi');
    const tahunFilter = document.querySelector('#tahunFilter');
    const statTotalProvinsi = document.querySelector('#statTotalProvinsi');
    const statTotalPendaftar = document.querySelector('#statTotalPendaftar');
    const statTotalKuota = document.querySelector('#statTotalKuota');
    const statRataLolos = document.querySelector('#statRataLolos');
    const cariProvinsiInput = document.querySelector('#cariProvinsi');
    const cariProvinsiPanel = document.querySelector('#cariProvinsiPanel');

    const kodeProdi = getQueryParam('prodi') || '';

    let provinsiList = [];
    let riwayatList = [];
    let currentProdi = null;

    function rowsForCurrentSelection() {
      const tahun = tahunFilter ? Number(tahunFilter.value) : null;
      const searchTerm = cariProvinsiInput ? cariProvinsiInput.value.trim().toLowerCase() : '';

      return riwayatList
        .filter(function (row) {
          return row.kode_prodi === kodeProdi && (tahun === null || row.tahun === tahun);
        })
        .map(function (row) {
          const provinsi = provinsiList.find(function (p) { return p.kode_provinsi === row.kode_provinsi; });
          return Object.assign({}, row, {
            nama_provinsi: provinsi ? provinsi.nama_provinsi : row.kode_provinsi
          });
        })
        .filter(function (row) {
          return searchTerm === '' || row.nama_provinsi.toLowerCase().indexOf(searchTerm) !== -1;
        })
        .sort(function (a, b) { return a.nama_provinsi.localeCompare(b.nama_provinsi); });
    }

    function rowTemplate(row, nomor) {
      const persenValue = computePersentaseLolos(row);
      const persentase = formatDecimal(persenValue, 2);
      const detailUrl = 'detail.html?prodi=' + encodeURIComponent(row.kode_prodi) +
        '&provinsi=' + encodeURIComponent(row.kode_provinsi);
      const zebraClass = nomor % 2 === 0 ? 'bg-slate-50/60' : 'bg-white';

      return '' +
        '<tr data-kode-provinsi="' + row.kode_provinsi + '" class="' + zebraClass + ' border-b border-slate-100 transition last:border-0 hover:bg-blue-50/40">' +
        '<td class="px-4 py-3 text-sm text-slate-500">' + nomor + '</td>' +
        '<td class="px-4 py-3 text-sm font-medium text-slate-800">' + row.nama_provinsi + '</td>' +
        '<td class="px-4 py-3 text-sm text-slate-700">' + formatNumber(row.jumlah_pendaftar) + '</td>' +
        '<td class="px-4 py-3 text-sm text-slate-700">' + formatNumber(row.kuota) + '</td>' +
        '<td class="px-4 py-3 text-sm">' +
        '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' + persenBadgeClass(persenValue) + '">' + persentase + '%</span>' +
        '</td>' +
        '<td class="px-4 py-3 text-center">' +
        '<a href="' + detailUrl + '&jenis=SKD" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Lihat</a>' +
        '</td>' +
        '<td class="px-4 py-3 text-center">' +
        '<a href="' + detailUrl + '&jenis=Matematika" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Lihat</a>' +
        '</td>' +
        '</tr>';
    }

    function renderStats(rows) {
      if (!statTotalProvinsi) {
        return;
      }
      const totalPendaftar = rows.reduce(function (sum, row) { return sum + row.jumlah_pendaftar; }, 0);
      const totalKuota = rows.reduce(function (sum, row) { return sum + row.kuota; }, 0);
      const rataLolos = rows.length > 0 ?
        rows.reduce(function (sum, row) { return sum + computePersentaseLolos(row); }, 0) / rows.length :
        0;

      statTotalProvinsi.textContent = formatNumber(rows.length);
      statTotalPendaftar.textContent = formatNumber(totalPendaftar);
      statTotalKuota.textContent = formatNumber(totalKuota);
      statRataLolos.textContent = formatDecimal(rataLolos, 2) + '%';
    }

    function render() {
      const rows = rowsForCurrentSelection();
      const searchTerm = cariProvinsiInput ? cariProvinsiInput.value.trim() : '';

      tableBody.innerHTML = rows.map(function (row, idx) { return rowTemplate(row, idx + 1); }).join('');

      if (emptyState) {
        emptyState.classList.toggle('hidden', rows.length > 0);
        emptyState.textContent = searchTerm !== '' ?
          'Tidak ada provinsi yang cocok dengan pencarian "' + searchTerm + '".' :
          'Belum ada data pesaing untuk program studi dan tahun ini.';
      }

      renderStats(rows);
    }

    function populateTahunFilter() {
      if (!tahunFilter) {
        return;
      }
      const years = Array.from(new Set(
        riwayatList.filter(function (row) { return row.kode_prodi === kodeProdi; })
          .map(function (row) { return row.tahun; })
      )).sort(function (a, b) { return b - a; });

      tahunFilter.innerHTML = years.map(function (year) {
        return '<option value="' + year + '">' + year + '</option>';
      }).join('');

      const requestedTahun = getQueryParam('tahun');
      const defaultTahun = requestedTahun && years.indexOf(Number(requestedTahun)) !== -1 ?
        requestedTahun :
        String(years[0] || '');

      tahunFilter.value = defaultTahun;
    }

    Promise.all([getProdiList(), getProvinsiList(), getRiwayatList()])
      .then(function (results) {
        const prodiList = results[0];
        provinsiList = results[1];
        riwayatList = results[2];
        currentProdi = prodiList.find(function (p) { return p.kode_prodi === kodeProdi; }) || null;

        if (heading) {
          heading.textContent = currentProdi ? currentProdi.nama_prodi : 'Program studi tidak ditemukan';
        }
        if (breadcrumb) {
          breadcrumb.textContent = currentProdi ? currentProdi.nama_prodi : kodeProdi;
        }

        populateTahunFilter();
        render();
      })
      .catch(function () {
        tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-600">Gagal memuat data pesaing.</td></tr>';
      });

    if (tahunFilter) {
      tahunFilter.addEventListener('change', render);
    }

    if (cariProvinsiInput) {
      cariProvinsiInput.addEventListener('input', render);
    }

    if (cariProvinsiInput && cariProvinsiPanel) {
      LiveSearch.create({
        inputEl: cariProvinsiInput,
        panelEl: cariProvinsiPanel,
        buildUrl: buildProvinsiUrl,
        mapResponse: mapProvinsiResponse,
        renderLabel: function (item) { return item.nama_provinsi; },
        emptyText: 'Provinsi tidak ditemukan.',
        onSelect: function (item) {
          render();

          const row = tableBody.querySelector('[data-kode-provinsi="' + item.kode_provinsi + '"]');

          if (notFoundNotice) {
            notFoundNotice.classList.toggle('hidden', Boolean(row));
            notFoundNotice.textContent = row ?
              '' :
              'Tidak ada data pesaing untuk ' + item.nama_provinsi + ' pada tahun/prodi yang sedang dipilih.';
          }

          flashElement(row);
        }
      });
    }
  }

  /* ------------------------------------------------------------- *
   * Halaman: Detail Riwayat Nilai (detail.html)
   * ------------------------------------------------------------- */

  function initDetailPage() {
    const yearCardsContainer = document.querySelector('#yearCardsContainer');

    if (!yearCardsContainer) {
      return;
    }

    const heading = document.querySelector('#pageHeading');
    const notFoundState = document.querySelector('#detailNotFoundState');
    const tabSkd = document.querySelector('#tabSkd');
    const tabMatematika = document.querySelector('#tabMatematika');

    const kodeProdi = getQueryParam('prodi') || '';
    const kodeProvinsi = getQueryParam('provinsi') || '';
    const requestedJenis = getQueryParam('jenis') === 'Matematika' ? 'Matematika' : 'SKD';

    let currentJenis = requestedJenis;
    let matchingRows = [];
    let chartInstance = null;

    function yearCardTemplate(row) {
      // "nilai" bisa saja belum lengkap (baris riwayat_nilai belum diinput
      // admin untuk tahun ini) karena api/riwayat.php sengaja memakai LEFT
      // JOIN supaya baris seperti itu tetap tampil, bukan disembunyikan.
      const nilai = row.nilai ? row.nilai[currentJenis] : null;
      const persentase = formatDecimal(computePersentaseLolos(row), 2);
      const digits = 2;

      const nilaiSection = nilai ?
        '<div class="flex items-center justify-between py-1.5"><dt class="text-slate-500">Nilai Tertinggi</dt><dd class="font-medium text-emerald-600">' + formatDecimal(nilai.tertinggi, digits) + '</dd></div>' +
        '<div class="flex items-center justify-between py-1.5"><dt class="text-slate-500">Nilai Terendah</dt><dd class="font-medium text-red-500">' + formatDecimal(nilai.terendah, digits) + '</dd></div>' +
        '<div class="flex items-center justify-between py-1.5"><dt class="text-slate-500">Rata-rata</dt><dd class="font-medium text-slate-800">' + formatDecimal(nilai.rata_rata, digits) + '</dd></div>' :
        '<div class="py-2 text-center text-xs italic text-slate-400">Data nilai ' + currentJenis + ' belum diinput untuk tahun ini.</div>';

      return '' +
        '<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">' +
        '<div class="rounded-t-2xl bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white">' + row.tahun + '</div>' +
        '<dl class="divide-y divide-slate-100 px-4 py-3 text-sm">' +
        '<div class="flex items-center justify-between py-1.5"><dt class="text-slate-500">Pendaftar</dt><dd class="font-medium text-slate-800">' + formatNumber(row.jumlah_pendaftar) + '</dd></div>' +
        '<div class="flex items-center justify-between py-1.5"><dt class="text-slate-500">Kuota</dt><dd class="font-medium text-slate-800">' + formatNumber(row.kuota) + '</dd></div>' +
        '<div class="flex items-center justify-between py-1.5"><dt class="text-slate-500">Persentase Lolos</dt><dd class="font-medium text-slate-800">' + persentase + '%</dd></div>' +
        nilaiSection +
        '</dl>' +
        '</div>';
    }

    function renderTrenSkdChart(rows) {
      const canvas = document.querySelector('#trenSkdChart');
      const card = document.querySelector('#trenSkdCard');

      if (!canvas || !card || typeof Chart === 'undefined') {
        return;
      }

      const skdRows = rows.filter(function (row) { return row.nilai && row.nilai.SKD; });

      if (skdRows.length === 0) {
        card.classList.add('hidden');
        return;
      }

      card.classList.remove('hidden');

      const labels = skdRows.map(function (row) { return String(row.tahun); });
      const dataPoints = skdRows.map(function (row) { return row.nilai.SKD.rata_rata; });

      if (chartInstance) {
        chartInstance.destroy();
      }

      chartInstance = new Chart(canvas, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Rata-rata Nilai SKD',
            data: dataPoints,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#2563eb'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (context) { return 'Rata-rata SKD: ' + formatDecimal(context.parsed.y, 2); }
              }
            }
          },
          scales: {
            y: {
              suggestedMin: 0,
              suggestedMax: 550,
              ticks: { stepSize: 100 }
            }
          }
        }
      });
    }

    function renderTabs() {
      if (!tabSkd || !tabMatematika) {
        return;
      }
      const activeClasses = ['bg-blue-600', 'text-white'];
      const inactiveClasses = ['bg-white', 'text-slate-600'];

      [tabSkd, tabMatematika].forEach(function (tab) {
        tab.classList.remove.apply(tab.classList, activeClasses.concat(inactiveClasses));
      });

      const activeTab = currentJenis === 'SKD' ? tabSkd : tabMatematika;
      const inactiveTab = currentJenis === 'SKD' ? tabMatematika : tabSkd;

      activeTab.classList.add.apply(activeTab.classList, activeClasses);
      inactiveTab.classList.add.apply(inactiveTab.classList, inactiveClasses);
    }

    function render() {
      renderTabs();
      yearCardsContainer.innerHTML = matchingRows.map(yearCardTemplate).join('');
    }

    function setJenis(jenis) {
      currentJenis = jenis;
      render();
    }

    Promise.all([getProdiList(), getProvinsiList(), getRiwayatList()])
      .then(function (results) {
        const prodiList = results[0];
        const provinsiList = results[1];
        const riwayatList = results[2];

        const prodi = prodiList.find(function (p) { return p.kode_prodi === kodeProdi; });
        const provinsi = provinsiList.find(function (p) { return p.kode_provinsi === kodeProvinsi; });

        matchingRows = riwayatList
          .filter(function (row) { return row.kode_prodi === kodeProdi && row.kode_provinsi === kodeProvinsi; })
          .sort(function (a, b) { return a.tahun - b.tahun; });

        if (heading) {
          heading.textContent = (prodi ? prodi.nama_prodi : kodeProdi) + ' - ' + (provinsi ? provinsi.nama_provinsi : kodeProvinsi);
        }

        if (matchingRows.length === 0) {
          yearCardsContainer.classList.add('hidden');
          if (notFoundState) {
            notFoundState.classList.remove('hidden');
            notFoundState.textContent = 'Belum ada riwayat data untuk kombinasi program studi dan provinsi ini.';
          }
          return;
        }

        renderTrenSkdChart(matchingRows);
        render();
      })
      .catch(function () {
        if (notFoundState) {
          notFoundState.classList.remove('hidden');
          notFoundState.textContent = 'Gagal memuat data riwayat nilai.';
        }
      });

    if (tabSkd) {
      tabSkd.addEventListener('click', function () { setJenis('SKD'); });
    }
    if (tabMatematika) {
      tabMatematika.addEventListener('click', function () { setJenis('Matematika'); });
    }
  }

  /* ------------------------------------------------------------- *
   * Halaman: Bandingkan Data (bandingkan.html)
   * ------------------------------------------------------------- */

  function initBandingkanPage() {
    const selectProdi = document.querySelector('#bdProdi');
    const selectProvinsi = document.querySelector('#bdProvinsi');
    const selectTahun = document.querySelector('#bdTahun');
    const tambahBtn = document.querySelector('#bdTambah');

    if (!selectProdi || !selectProvinsi || !selectTahun || !tambahBtn) {
      return;
    }

    const notice = document.querySelector('#bdSelectorNotice');
    const tableBody = document.querySelector('#bdTableBody');
    const emptyState = document.querySelector('#bdEmptyState');
    const chartCard = document.querySelector('#bdChartCard');
    const chartCanvas = document.querySelector('#bdChart');

    const MAX_BARIS = 5;
    const SERIES_COLORS = { skd: '#2563eb', matematika: '#f59e0b' };

    let riwayatList = [];
    let comparisonRows = [];
    let chartInstance = null;

    function showNotice(message) {
      if (!notice) {
        return;
      }
      if (!message) {
        notice.classList.add('hidden');
        notice.textContent = '';
        return;
      }
      notice.textContent = message;
      notice.classList.remove('hidden');
    }

    function populateSelects(prodiList, provinsiList, years) {
      selectProdi.innerHTML = prodiList.map(function (p) {
        return '<option value="' + p.kode_prodi + '">' + escapeHtml(p.nama_prodi) + '</option>';
      }).join('');

      selectProvinsi.innerHTML = provinsiList.map(function (v) {
        return '<option value="' + v.kode_provinsi + '">' + escapeHtml(v.kode_provinsi) + ' · ' + escapeHtml(v.nama_provinsi) + '</option>';
      }).join('');

      selectTahun.innerHTML = years.map(function (tahun) {
        return '<option value="' + tahun + '">' + tahun + '</option>';
      }).join('');
    }

    function rowKey(row) {
      return row.kode_prodi + '|' + row.kode_provinsi + '|' + row.tahun;
    }

    function renderTable() {
      if (comparisonRows.length === 0) {
        tableBody.innerHTML = '';
        if (emptyState) {
          emptyState.classList.remove('hidden');
        }
        if (chartCard) {
          chartCard.classList.add('hidden');
        }
        return;
      }

      if (emptyState) {
        emptyState.classList.add('hidden');
      }

      tableBody.innerHTML = comparisonRows.map(function (row, index) {
        const persenValue = computePersentaseLolos(row);
        const skd = row.nilai && row.nilai.SKD ? formatDecimal(row.nilai.SKD.rata_rata, 2) : '<span class="italic text-slate-400">&mdash;</span>';
        const mtk = row.nilai && row.nilai.Matematika ? formatDecimal(row.nilai.Matematika.rata_rata, 2) : '<span class="italic text-slate-400">&mdash;</span>';
        const zebraClass = index % 2 === 0 ? 'bg-white' : 'bg-slate-50/60';
        const theme = prodiColorTheme(row.kode_prodi);

        return '' +
          '<tr class="border-l-4 ' + theme.bar.replace('bg-', 'border-l-') + ' ' + zebraClass + ' border-b border-slate-100 last:border-0">' +
          '<td class="px-4 py-3 text-sm">' +
          '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' + theme.badge + '">' + escapeHtml(row.kode_prodi) + '</span>' +
          '</td>' +
          '<td class="px-4 py-3 text-sm text-slate-700">' + escapeHtml(row.nama_provinsi) + '</td>' +
          '<td class="px-4 py-3 text-sm text-slate-700">' + row.tahun + '</td>' +
          '<td class="px-4 py-3 text-right text-sm text-slate-700">' + formatNumber(row.jumlah_pendaftar) + '</td>' +
          '<td class="px-4 py-3 text-right text-sm text-slate-700">' + formatNumber(row.kuota) + '</td>' +
          '<td class="px-4 py-3 text-right text-sm">' +
          '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' + persenBadgeClass(persenValue) + '">' + formatDecimal(persenValue, 2) + '%</span>' +
          '</td>' +
          '<td class="px-4 py-3 text-right text-sm text-slate-600">' + skd + '</td>' +
          '<td class="px-4 py-3 text-right text-sm text-slate-600">' + mtk + '</td>' +
          '<td class="px-4 py-3 text-center text-sm">' +
          '<button type="button" data-hapus-index="' + index + '" class="font-medium text-red-600 hover:underline">Hapus</button>' +
          '</td>' +
          '</tr>';
      }).join('');

      tableBody.querySelectorAll('[data-hapus-index]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const idx = Number(btn.getAttribute('data-hapus-index'));
          comparisonRows.splice(idx, 1);
          renderTable();
          renderChart();
        });
      });
    }

    function renderChart() {
      if (!chartCard || !chartCanvas) {
        return;
      }
      if (comparisonRows.length === 0 || typeof Chart === 'undefined') {
        chartCard.classList.add('hidden');
        return;
      }

      chartCard.classList.remove('hidden');

      const labels = comparisonRows.map(function (row) {
        return row.kode_prodi + ' · ' + row.kode_provinsi + ' · ' + row.tahun;
      });
      const skdData = comparisonRows.map(function (row) {
        return row.nilai && row.nilai.SKD ? row.nilai.SKD.rata_rata : null;
      });
      const mtkData = comparisonRows.map(function (row) {
        return row.nilai && row.nilai.Matematika ? row.nilai.Matematika.rata_rata : null;
      });

      if (chartInstance) {
        chartInstance.destroy();
      }

      chartInstance = new Chart(chartCanvas, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Rata-rata SKD (skala 0–550)',
              data: skdData,
              backgroundColor: SERIES_COLORS.skd,
              borderRadius: 4,
              yAxisID: 'ySkd'
            },
            {
              label: 'Rata-rata Matematika (skala 0–100)',
              data: mtkData,
              backgroundColor: SERIES_COLORS.matematika,
              borderRadius: 4,
              yAxisID: 'yMtk'
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          },
          scales: {
            ySkd: { type: 'linear', position: 'left', suggestedMin: 0, suggestedMax: 550, title: { display: true, text: 'SKD' } },
            yMtk: { type: 'linear', position: 'right', suggestedMin: 0, suggestedMax: 100, title: { display: true, text: 'Matematika' }, grid: { drawOnChartArea: false } }
          }
        }
      });
    }

    tambahBtn.addEventListener('click', function () {
      const kodeProdi = selectProdi.value;
      const kodeProvinsi = selectProvinsi.value;
      const tahun = Number(selectTahun.value);

      const match = riwayatList.find(function (row) {
        return row.kode_prodi === kodeProdi && row.kode_provinsi === kodeProvinsi && row.tahun === tahun;
      });

      if (!match) {
        showNotice('Data untuk kombinasi ini belum tersedia di riwayat -- kemungkinan admin belum menginput data tahun tersebut.');
        return;
      }

      if (comparisonRows.length >= MAX_BARIS) {
        showNotice('Maksimal ' + MAX_BARIS + ' baris perbandingan. Hapus salah satu baris dulu untuk menambah yang baru.');
        return;
      }

      const alreadyAdded = comparisonRows.some(function (row) { return rowKey(row) === rowKey(match); });
      if (alreadyAdded) {
        showNotice('Kombinasi ini sudah ada di daftar perbandingan.');
        return;
      }

      showNotice('');
      const provinsiEntry = selectProvinsi.options[selectProvinsi.selectedIndex];
      comparisonRows.push(Object.assign({}, match, {
        nama_provinsi: provinsiEntry ? provinsiEntry.textContent.split('·').pop().trim() : match.kode_provinsi
      }));
      renderTable();
      renderChart();
    });

    Promise.all([getProdiList(), getProvinsiList(), getRiwayatList()])
      .then(function (results) {
        const prodiList = results[0];
        const provinsiList = results[1];
        riwayatList = results[2];

        const years = Array.from(new Set(riwayatList.map(function (row) { return row.tahun; })))
          .sort(function (a, b) { return b - a; });

        populateSelects(prodiList, provinsiList, years);
        renderTable();
      })
      .catch(function () {
        showNotice('Gagal memuat data untuk perbandingan.');
      });
  }

  /* ------------------------------------------------------------- *
   * Bootstrap
   * ------------------------------------------------------------- */

  function main() {
    initHeaderAuthState();

    const page = document.body.getAttribute('data-page');

    if (page === 'dashboard') {
      initDashboardPage();
    } else if (page === 'pesaing') {
      initPesaingPage();
    } else if (page === 'detail') {
      initDetailPage();
    } else if (page === 'bandingkan') {
      initBandingkanPage();
    }
  }

  document.addEventListener('DOMContentLoaded', main);

}());
