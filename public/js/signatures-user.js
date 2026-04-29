(function () {
   'use strict';

   const COOKIE = 'sig_download_done';

   function hasCookie(name) {
      return document.cookie.split(';').some(c => c.trim().startsWith(name + '='));
   }
   function clearCookie(name) {
      document.cookie = name + '=; path=/; max-age=0; SameSite=Strict';
   }

   function readCfg() {
      const el = document.getElementById('sig-user-data');
      if (!el) return null;
      try { return JSON.parse(el.dataset.cfg); } catch (_) { return {}; }
   }

   // ── Vanilla delegation (once per page load) ─────────────────────────────
   function setupDelegation() {
      if (window._sigUserMounted) return;
      window._sigUserMounted = true;

      document.addEventListener('change', function (e) {
         const chk = e.target.closest('#qr_check');
         if (!chk) return;
         const val = chk.checked ? '1' : '';
         const dl  = document.getElementById('qr_download');
         const sn  = document.getElementById('qr_send');
         const btn = document.getElementById('btn-preview-sig');
         if (dl)  dl.value          = val;
         if (sn)  sn.value          = val;
         if (btn) btn.dataset.qrval = val;
      });

      document.addEventListener('submit', function (e) {
         if (e.target.id === 'form-download') {
            const btn = e.target.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            const ic = btn.querySelector('i'), orig = ic?.className || 'ti ti-download me-2';
            btn.disabled = true; if (ic) ic.className = 'spinner-border spinner-border-sm me-2';
            clearCookie(COOKIE); let t = 0;
            const p = setInterval(() => { t += 400; if (hasCookie(COOKIE) || t > 30000) { clearInterval(p); clearCookie(COOKIE); btn.disabled = false; if (ic) ic.className = orig; } }, 400);
         }
         if (e.target.id === 'form-send') {
            const btn = e.target.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            const ic = btn.querySelector('i'); if (ic) ic.className = 'spinner-border spinner-border-sm me-2';
         }
      });

      document.addEventListener('click', function (e) {
         const btn = e.target.closest('#btn-preview-sig');
         if (!btn || btn.disabled) return;
         const modalEl = document.getElementById('sig-preview-modal');
         if (!modalEl) return;
         const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
         const img     = document.getElementById('sig-preview-img');
         const loading = document.getElementById('sig-preview-loading');
         const qrCheck = document.getElementById('qr_check');
         const qrVal   = qrCheck ? (qrCheck.checked ? '1' : '') : btn.dataset.qrval;
         const i18n    = window._sigUserI18n || {};
         const url     = btn.dataset.downloadUrl + '?userid=' + btn.dataset.userid + '&include_qr=' + qrVal + '&preview=1';
         img.style.display = 'none'; img.src = ''; loading.style.display = ''; modal.show();
         img.onload  = () => { loading.style.display = 'none'; img.style.display = ''; };
         img.onerror = () => { loading.style.display = 'none'; img.alt = i18n.previewError || 'Error'; img.style.display = ''; };
         img.src = url;
      });
   }

   // ── Init when tab data element appears in DOM ───────────────────────────
   function tryInit() {
      const cfg = readCfg();
      if (!cfg) return false;
      window._sigUserI18n = cfg.i18n || {};
      setupDelegation();
      return true;
   }

   function observe() {
      if (tryInit()) return;
      const obs = new MutationObserver(() => { if (tryInit()) obs.disconnect(); });
      obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
      let n = 0;
      const t = setInterval(() => { if (tryInit() || ++n > 75) clearInterval(t); }, 400);
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', observe);
   } else {
      observe();
   }
})();
