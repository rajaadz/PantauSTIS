/* jshint esversion: 11, browser: true, devel: true, undef: true, unused: true, curly: true, eqeqeq: true */
/* globals fetch, AbortController */

/**
 * live-search.js
 * ---------------------------------------------------------------------
 * Komponen live-search / suggestion generik untuk Tracker SPMB STIS
 * (PantauSTIS). Dipakai untuk pencarian Program Studi dan Provinsi,
 * dan bisa dipakai ulang untuk field pencarian lain di masa depan.
 *
 * Cara pakai:
 *
 *   const prodiSearch = LiveSearch.create({
 *     inputEl: document.querySelector('#cariProdi'),
 *     panelEl: document.querySelector('#cariProdiPanel'),
 *     buildUrl: function (query) {
 *       return 'api/cari_prodi.php?q=' + encodeURIComponent(query);
 *     },
 *     mapResponse: function (json) { return json.data; },
 *     renderLabel: function (item) { return item.nama_prodi; },
 *     renderMeta: function (item) { return item.jenjang; },
 *     onSelect: function (item) {
 *       window.location.href = 'pesaing.html?prodi=' + item.kode_prodi;
 *     }
 *   });
 *
 * Kontrak endpoint PHP yang diharapkan (bentuk JSON):
 *   { "data": [ { "kode_prodi": "...", "nama_prodi": "...", "jenjang": "..." }, ... ] }
 *
 * Tidak ada dependency di luar Fetch API dan DOM standar. Setiap
 * pemanggilan LiveSearch.create() menghasilkan instance independen,
 * jadi aman dipakai lebih dari sekali pada satu halaman (mis. satu
 * untuk Prodi, satu untuk Provinsi).
 * ---------------------------------------------------------------------
 */
(function () {
  'use strict';

  const DEFAULT_MIN_CHARS = 2;
  const DEFAULT_DEBOUNCE_MS = 250;
  const ACTIVE_CLASSES = ['bg-blue-50', 'text-blue-700'];

  /**
   * Meng-escape karakter HTML supaya data dari server tidak pernah
   * dirender sebagai markup (mencegah XSS lewat hasil pencarian).
   */
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    };
    return String(text).replace(/[&<>"']/g, function (ch) {
      return map[ch];
    });
  }

  /** Menyorot bagian teks yang cocok dengan query pencarian. */
  function highlightMatch(label, query) {
    const safeLabel = escapeHtml(label);
    const safeQuery = escapeHtml(query).trim();

    if (safeQuery === '') {
      return safeLabel;
    }

    const escapedForRegex = safeQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp('(' + escapedForRegex + ')', 'ig');

    return safeLabel.replace(pattern, '<mark class="bg-blue-100 text-blue-800 rounded-sm px-0.5">$1</mark>');
  }

  function create(options) {
    if (!options || !options.inputEl || !options.panelEl || typeof options.buildUrl !== 'function') {
      throw new Error('LiveSearch.create: inputEl, panelEl, dan buildUrl wajib diisi.');
    }

    const inputEl = options.inputEl;
    const panelEl = options.panelEl;
    const buildUrl = options.buildUrl;
    const mapResponse = options.mapResponse || function (json) { return json; };
    const renderLabel = options.renderLabel || function (item) { return String(item); };
    const renderMeta = options.renderMeta || null;
    const onSelect = options.onSelect || function () {};
    const minChars = options.minChars || DEFAULT_MIN_CHARS;
    const debounceMs = options.debounceMs || DEFAULT_DEBOUNCE_MS;
    const emptyText = options.emptyText || 'Tidak ada hasil ditemukan.';
    const errorText = options.errorText || 'Gagal memuat data. Silakan coba lagi.';

    let debounceTimer = null;
    let activeController = null;
    let currentItems = [];
    let activeIndex = -1;
    let lastQuery = '';

    function closePanel() {
      panelEl.innerHTML = '';
      panelEl.classList.add('hidden');
      currentItems = [];
      activeIndex = -1;
    }

    function openPanel() {
      panelEl.classList.remove('hidden');
    }

    function setActive(index) {
      const optionEls = panelEl.querySelectorAll('[data-ls-option]');
      if (optionEls.length === 0) {
        return;
      }

      activeIndex = (index + optionEls.length) % optionEls.length;

      optionEls.forEach(function (el, idx) {
        if (idx === activeIndex) {
          el.classList.add.apply(el.classList, ACTIVE_CLASSES);
          el.setAttribute('aria-selected', 'true');
          el.scrollIntoView({ block: 'nearest' });
        } else {
          el.classList.remove.apply(el.classList, ACTIVE_CLASSES);
          el.setAttribute('aria-selected', 'false');
        }
      });
    }

    function selectItem(item) {
      closePanel();
      inputEl.value = renderLabel(item);
      onSelect(item);
    }

    function renderLoading() {
      panelEl.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">Memuat...</div>';
      openPanel();
    }

    function renderError() {
      panelEl.innerHTML = '<div class="px-4 py-3 text-sm text-red-600">' + escapeHtml(errorText) + '</div>';
      openPanel();
    }

    function renderEmpty() {
      panelEl.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">' + escapeHtml(emptyText) + '</div>';
      openPanel();
    }

    function renderItems(items, query) {
      currentItems = items;
      activeIndex = -1;

      if (items.length === 0) {
        renderEmpty();
        return;
      }

      const html = items.map(function (item, idx) {
        const label = highlightMatch(renderLabel(item), query);
        const meta = renderMeta ?
          '<span class="block text-xs text-slate-400">' + escapeHtml(renderMeta(item)) + '</span>' :
          '';

        return '' +
          '<button type="button" role="option" data-ls-option data-ls-index="' + idx + '" ' +
          'class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none transition-colors">' +
          '<span class="block font-medium">' + label + '</span>' +
          meta +
          '</button>';
      }).join('');

      panelEl.innerHTML = html;
      openPanel();

      panelEl.querySelectorAll('[data-ls-option]').forEach(function (el) {
        el.addEventListener('mousedown', function (event) {
          event.preventDefault();
          const idx = Number(el.getAttribute('data-ls-index'));
          selectItem(currentItems[idx]);
        });
      });
    }

    function fetchSuggestions(query) {
      if (activeController) {
        activeController.abort();
      }
      activeController = new AbortController();

      renderLoading();

      fetch(buildUrl(query), { signal: activeController.signal })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('HTTP ' + response.status);
          }
          return response.json();
        })
        .then(function (json) {
          const items = mapResponse(json, query) || [];
          if (query === lastQuery) {
            renderItems(items, query);
          }
        })
        .catch(function (error) {
          if (error && error.name === 'AbortError') {
            return;
          }
          renderError();
        });
    }

    function handleInput() {
      const query = inputEl.value.trim();
      lastQuery = query;

      window.clearTimeout(debounceTimer);

      if (query.length < minChars) {
        closePanel();
        return;
      }

      debounceTimer = window.setTimeout(function () {
        fetchSuggestions(query);
      }, debounceMs);
    }

    function handleKeydown(event) {
      const key = event.key;

      if (key === 'ArrowDown') {
        event.preventDefault();
        if (!panelEl.classList.contains('hidden')) {
          setActive(activeIndex + 1);
        }
        return;
      }

      if (key === 'ArrowUp') {
        event.preventDefault();
        if (!panelEl.classList.contains('hidden')) {
          setActive(activeIndex - 1);
        }
        return;
      }

      if (key === 'Enter') {
        if (activeIndex >= 0 && currentItems[activeIndex]) {
          event.preventDefault();
          selectItem(currentItems[activeIndex]);
        }
        return;
      }

      if (key === 'Escape') {
        closePanel();
      }
    }

    function handleFocusOut(event) {
      const next = event.relatedTarget;
      if (next && panelEl.contains(next)) {
        return;
      }
      window.setTimeout(closePanel, 100);
    }

    inputEl.addEventListener('input', handleInput);
    inputEl.addEventListener('keydown', handleKeydown);
    inputEl.addEventListener('focusout', handleFocusOut);

    function destroy() {
      inputEl.removeEventListener('input', handleInput);
      inputEl.removeEventListener('keydown', handleKeydown);
      inputEl.removeEventListener('focusout', handleFocusOut);
      window.clearTimeout(debounceTimer);
      if (activeController) {
        activeController.abort();
      }
      closePanel();
    }

    return {
      destroy: destroy,
      close: closePanel
    };
  }

  window.LiveSearch = {
    create: create
  };

}());
