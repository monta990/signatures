(function () {
   'use strict';

   function init() {
      const el = document.getElementById('sig-config-data');
      if (!el) return false;
      if (window._sigConfigInited) return true;
      window._sigConfigInited = true;

      let cfg;
      try { cfg = JSON.parse(el.dataset.cfg); } catch (_) { cfg = {}; }

      const ASCENT   = cfg.ascent   || 0.72;
      const DEFAULTS = cfg.defaults || {};
      const I18N     = cfg.i18n    || {};

      // ── @font-face injection ───────────────────────────────────────────────
      if (cfg.fontBlackUrl || cfg.fontRomanUrl) {
         const s = document.createElement('style');
         s.textContent = [
            `@font-face{font-family:'AvenirBlack';src:url('${cfg.fontBlackUrl}');font-weight:normal;font-style:normal;}`,
            `@font-face{font-family:'AvenirRoman';src:url('${cfg.fontRomanUrl}');font-weight:normal;font-style:normal;}`,
         ].join('');
         document.head.appendChild(s);
      }

      // ── Scale helpers ──────────────────────────────────────────────────────
      function getScale(wrap) {
         const img = wrap && wrap.querySelector('img');
         if (!img || !img.naturalWidth) return 1;
         const s = img.clientWidth / img.naturalWidth;
         return s > 0 ? s : 1;
      }

      function applyScale(baseId) {
         const wrap = document.getElementById('editor-' + baseId);
         if (!wrap) return;
         const scale  = getScale(wrap);
         wrap.dataset.scale = scale;
         const imgEl  = wrap.querySelector('img.sig-bg');

         wrap.querySelectorAll('.sig-field').forEach(el => {
            const gdX  = parseFloat(el.dataset.gdX ?? el.offsetLeft);
            const gdY  = parseFloat(el.dataset.gdY ?? el.offsetTop);
            const isQr = el.dataset.isQr === '1';
            const size = parseInt(el.dataset.fontSize || '11');

            el.style.left = (gdX * scale) + 'px';
            el.style.top  = (isQr
               ? gdY * scale
               : Math.max(0, (gdY - size * ASCENT) * scale)) + 'px';

            if (isQr) {
               const px   = Math.round(100 * scale);
               el.style.width  = px + 'px';
               el.style.height = px + 'px';
               const icon = el.querySelector('i');
               if (icon) icon.style.fontSize = Math.round(px * 0.45) + 'px';
            } else {
               el.style.fontSize = (size * scale) + 'px';
            }

            if (imgEl && imgEl.clientWidth > 0) {
               const maxCssX = Math.max(0, imgEl.clientWidth  - el.offsetWidth);
               const maxCssY = Math.max(0, imgEl.clientHeight - el.offsetHeight);
               el.dataset.maxGdX = Math.round(maxCssX / scale);
               el.dataset.maxGdY = Math.round(maxCssY / scale) + (isQr ? 0 : Math.round(size * ASCENT));
            }
         });
      }

      function syncInputs(el, cssLeft, cssTop) {
         const wrap  = el.closest('.sig-editor-wrap');
         const scale = parseFloat(wrap?.dataset.scale || '1') || 1;
         const base  = el.dataset.base;
         const field = el.dataset.field;
         const isQr  = el.dataset.isQr === '1';
         const size  = isQr ? 0 : parseInt(el.dataset.fontSize || '11');

         const gdX = Math.round(cssLeft / scale);
         const gdY = isQr
            ? Math.round(cssTop / scale)
            : Math.round(cssTop / scale + size * ASCENT);

         el.dataset.gdX = gdX;
         el.dataset.gdY = gdY;

         const posX = document.getElementById('pos-' + base + '-' + field + '-x');
         const posY = document.getElementById('pos-' + base + '-' + field + '-y');
         if (posX) posX.value = gdX;
         if (posY) posY.value = gdY;
      }

      // ── Dirty state ────────────────────────────────────────────────────────
      let _dirty = false;

      function markDirty() {
         if (_dirty) return;
         _dirty = true;
         const tabBtn = document.querySelector('[data-bs-target="#tab-positions"]');
         if (tabBtn && !tabBtn.querySelector('.sig-dirty-dot')) {
            const dot = document.createElement('span');
            dot.className  = 'sig-dirty-dot badge bg-warning text-dark ms-1 p-1';
            dot.style.fontSize = '0.6em';
            dot.title      = I18N.unsavedChanges || '';
            dot.textContent = '●';
            tabBtn.appendChild(dot);
         }
         const banner = document.getElementById('sig-unsaved-banner');
         if (banner) banner.classList.remove('d-none');
      }

      function clearDirty() {
         _dirty = false;
         document.querySelectorAll('.sig-dirty-dot').forEach(el => el.remove());
         const banner = document.getElementById('sig-unsaved-banner');
         if (banner) banner.classList.add('d-none');
      }

      // ── Drag & drop (mouse + touch) ────────────────────────────────────────
      (function initDrag() {
         let dragging = null, ox = 0, oy = 0, startL = 0, startT = 0;

         function coords(e) {
            const src = e.touches ? e.touches[0] : e;
            return { x: src.clientX, y: src.clientY };
         }

         function onStart(e) {
            const el = e.target.closest('.sig-field');
            if (!el) return;
            e.preventDefault();
            dragging = el;
            const c  = coords(e);
            ox = c.x; oy = c.y;
            startL = el.offsetLeft; startT = el.offsetTop;
            el.style.cursor = 'grabbing';
            el.style.zIndex = 999;
         }

         function onMove(e) {
            if (!dragging) return;
            e.preventDefault();
            const c      = coords(e);
            const editor = dragging.closest('.sig-editor-wrap');
            const img    = editor ? editor.querySelector('img.sig-bg') : null;
            const maxL   = img ? Math.max(0, img.clientWidth  - dragging.offsetWidth)  : 9999;
            const maxT   = img ? Math.max(0, img.clientHeight - dragging.offsetHeight) : 9999;
            const newL   = Math.min(maxL, Math.max(0, startL + c.x - ox));
            const newT   = Math.min(maxT, Math.max(0, startT + c.y - oy));
            dragging.style.left = newL + 'px';
            dragging.style.top  = newT + 'px';
            syncInputs(dragging, newL, newT);
            markDirty();
         }

         function onEnd() {
            if (!dragging) return;
            dragging.style.cursor = 'grab';
            dragging.style.zIndex = '';
            dragging = null;
         }

         document.addEventListener('mousedown',  onStart);
         document.addEventListener('mousemove',  onMove);
         document.addEventListener('mouseup',    onEnd);
         document.addEventListener('touchstart', onStart, { passive: false });
         document.addEventListener('touchmove',  onMove,  { passive: false });
         document.addEventListener('touchend',   onEnd);
      })();

      // ── Size input → canvas update ─────────────────────────────────────────
      document.addEventListener('input', e => {
         const inp = e.target;
         if (!inp.classList.contains('sig-size-input')) return;
         const base  = inp.dataset.base;
         const field = inp.dataset.field;
         const size  = parseInt(inp.value) || 11;

         const el    = document.getElementById('field-' + base + '-' + field);
         if (!el) return;
         const wrap  = el.closest('.sig-editor-wrap');
         const scale = parseFloat(wrap?.dataset.scale || '1') || 1;

         el.dataset.fontSize = size;
         el.style.fontSize   = (size * scale) + 'px';

         const inpS = document.getElementById('inp-' + base + '-' + field + '-size');
         if (inpS) inpS.value = size;

         syncInputs(el, el.offsetLeft, el.offsetTop);
         markDirty();
      });

      // ── X/Y position inputs → canvas update ───────────────────────────────
      document.addEventListener('input', e => {
         const inp = e.target;
         if (!inp.classList.contains('sig-pos-input')) return;
         const base  = inp.dataset.base;
         const field = inp.dataset.field;
         const isX   = inp.classList.contains('sig-pos-input-x');

         const el     = document.getElementById('field-' + base + '-' + field);
         if (!el) return;
         const editor = el.closest('.sig-editor-wrap');
         const scale  = parseFloat(editor?.dataset.scale || '1') || 1;
         const isQr   = el.dataset.isQr === '1';
         const size   = isQr ? 0 : parseInt(el.dataset.fontSize || '11');

         const maxGdX = el.dataset.maxGdX !== undefined
            ? parseInt(el.dataset.maxGdX)
            : (() => {
                 const img = editor ? editor.querySelector('img.sig-bg') : null;
                 return Math.round(Math.max(0, img ? img.clientWidth - el.offsetWidth : 9999) / scale);
              })();
         const maxGdY = el.dataset.maxGdY !== undefined
            ? parseInt(el.dataset.maxGdY)
            : (() => {
                 const img = editor ? editor.querySelector('img.sig-bg') : null;
                 return Math.round(Math.max(0, img ? img.clientHeight - el.offsetHeight : 9999) / scale)
                      + (isQr ? 0 : Math.round(size * ASCENT));
              })();

         const posXInp = document.getElementById('pos-' + base + '-' + field + '-x');
         const posYInp = document.getElementById('pos-' + base + '-' + field + '-y');
         let gdX = parseInt(posXInp?.value) || 0;
         let gdY = parseInt(posYInp?.value) || 0;

         gdX = Math.min(maxGdX, Math.max(0, gdX));
         gdY = Math.min(maxGdY, Math.max(0, gdY));

         if (isX && posXInp)  posXInp.value = gdX;
         else if (!isX && posYInp) posYInp.value = gdY;

         el.dataset.gdX = gdX;
         el.dataset.gdY = gdY;

         const cssL = gdX * scale;
         const cssT = isQr ? gdY * scale : Math.max(0, (gdY - size * ASCENT) * scale);
         el.style.left = cssL + 'px';
         el.style.top  = cssT + 'px';

         markDirty();
      });

      // ── Reset to defaults ──────────────────────────────────────────────────
      document.addEventListener('click', e => {
         const btn = e.target.closest('.sig-reset-btn');
         if (!btn) return;
         if (!confirm(I18N.confirmReset || 'Reset?')) return;

         const base  = btn.dataset.base;
         const defs  = DEFAULTS[base];
         if (!defs) return;

         const wrap  = document.getElementById('editor-' + base);
         const scale = parseFloat(wrap?.dataset.scale || '1') || 1;

         Object.entries(defs).forEach(([field, coords]) => {
            const el = document.getElementById('field-' + base + '-' + field);
            if (!el) return;
            const isQr = el.dataset.isQr === '1';
            const size = coords.size || 11;

            el.dataset.gdX = coords.x;
            el.dataset.gdY = coords.y;

            const cssL = coords.x * scale;
            const cssT = isQr ? coords.y * scale : Math.max(0, (coords.y - size * ASCENT) * scale);
            el.style.left = cssL + 'px';
            el.style.top  = cssT + 'px';

            if (isQr) {
               const px = Math.round(100 * scale);
               el.style.width  = px + 'px';
               el.style.height = px + 'px';
               const icon = el.querySelector('i');
               if (icon) icon.style.fontSize = Math.round(px * 0.45) + 'px';
            } else {
               el.dataset.fontSize = size;
               el.style.fontSize   = (size * scale) + 'px';
               const inpS      = document.getElementById('inp-' + base + '-' + field + '-size');
               const sizeInput = document.querySelector(`.sig-size-input[data-base="${base}"][data-field="${field}"]`);
               if (inpS)      inpS.value      = size;
               if (sizeInput) sizeInput.value = size;
            }
            syncInputs(el, cssL, cssT);
         });
         markDirty();
      });

      // ── Enable / disable field checkbox ───────────────────────────────────
      document.addEventListener('change', e => {
         const cb = e.target.closest('.sig-enable-cb');
         if (!cb) return;
         const el = document.getElementById('field-' + cb.dataset.base + '-' + cb.dataset.field);
         if (el) el.style.opacity = cb.checked ? '1' : '0.25';
         markDirty();
      });

      // ── Format toolbar (B / I / U) ────────────────────────────────────────
      document.addEventListener('click', e => {
         const btn = e.target.closest('.sig-fmt-btn');
         if (!btn) return;
         const wrap = btn.dataset.wrap;
         const wLen = wrap.length;
         const ta   = (document.activeElement?.tagName === 'TEXTAREA')
            ? document.activeElement
            : window._sigLastTextarea;
         if (!ta) return;
         ta.focus();

         const start  = ta.selectionStart;
         const end    = ta.selectionEnd;
         const sel    = ta.value.substring(start, end);
         const before = ta.value.substring(start - wLen, start);
         const after  = ta.value.substring(end, end + wLen);

         const wrappedInside  = sel.startsWith(wrap) && sel.endsWith(wrap) && sel.length >= wLen * 2 + 1;
         const wrappedOutside = before === wrap && after === wrap;

         let newStart, newEnd;
         if (wrappedInside) {
            const inner = sel.slice(wLen, sel.length - wLen);
            ta.setRangeText(inner, start, end, 'preserve');
            newStart = start;
            newEnd   = start + inner.length;
         } else if (wrappedOutside) {
            ta.setRangeText(sel, start - wLen, end + wLen, 'preserve');
            newStart = start - wLen;
            newEnd   = newStart + sel.length;
         } else {
            const inner = sel || (I18N.formatPlaceholder || 'text');
            ta.setRangeText(wrap + inner + wrap, start, end, 'preserve');
            newStart = start + wLen;
            newEnd   = newStart + inner.length;
         }
         ta.setSelectionRange(newStart, newEnd);
      });

      document.querySelectorAll('textarea[name="email_body"], textarea[name="email_footer"]')
         .forEach(ta => ta.addEventListener('focus', () => { window._sigLastTextarea = ta; }));

      // ── Variable badges ────────────────────────────────────────────────────
      let _lastField = null;
      ['email_subject', 'email_body', 'email_footer'].forEach(name => {
         const el = document.querySelector('[name="' + name + '"]');
         if (el) el.addEventListener('focus', () => { _lastField = el; });
      });

      document.addEventListener('click', e => {
         const badge = e.target.closest('.sig-var-badge');
         if (!badge) return;
         const varText = badge.dataset.var;
         const target  = _lastField || document.querySelector('[name="email_body"]');
         if (!target) return;
         const start = target.selectionStart ?? target.value.length;
         const end   = target.selectionEnd   ?? target.value.length;
         target.value = target.value.slice(0, start) + varText + target.value.slice(end);
         target.selectionStart = target.selectionEnd = start + varText.length;
         target.focus();
      });

      // ── Template file preview ──────────────────────────────────────────────
      window.preview = function (input, imgId, wrapId) {
         const file = input.files[0];
         if (!file) return;
         if (file.type !== 'image/png') {
            alert(I18N.onlyPng || 'Only PNG files are allowed');
            input.value = '';
            return;
         }
         const reader  = new FileReader();
         reader.onload = e => {
            document.getElementById(imgId).src = e.target.result;
            document.getElementById(wrapId).classList.remove('d-none');
         };
         reader.readAsDataURL(file);
      };

      // ── Delete-template / delete-font post buttons (replaces nested forms) ──
      document.addEventListener('click', e => {
         const btn = e.target.closest('[data-sig-post-url]');
         if (!btn) return;
         const msg = btn.dataset.sigPostConfirm || (I18N.confirmDelete || 'Delete?');
         if (!confirm(msg)) return;
         const f = document.createElement('form');
         f.method = 'post'; f.action = btn.dataset.sigPostUrl;
         const addHidden = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
         addHidden('_glpi_csrf_token', btn.dataset.sigPostCsrf);
         addHidden(btn.dataset.sigPostField, btn.dataset.sigPostValue);
         document.body.appendChild(f); f.submit();
      });

      // ── Font upload hint ───────────────────────────────────────────────────
      const fontInp  = document.getElementById('font_upload_input');
      const fontHint = document.getElementById('font_upload_name');
      if (fontInp && fontHint) {
         fontInp.addEventListener('change', function () {
            fontHint.style.display = this.files.length ? '' : 'none';
         });
      }

      // ── Save button spinner ────────────────────────────────────────────────
      document.addEventListener('submit', function (e) {
         if (!e.target.matches('form[method="post"]') || e.target.id === 'sig-test-mail-form') return;
         clearDirty();
         const icon = document.getElementById('icon-save-config');
         if (icon) icon.className = 'spinner-border spinner-border-sm me-1';
      });

      // ── Tab initialization & hash routing ─────────────────────────────────
      document.addEventListener('shown.bs.tab', function (e) {
         if (e.target.id !== 'btn-tab-positions') return;
         ['b1', 'b2'].forEach(id => {
            const wrap = document.getElementById('editor-' + id);
            const img  = wrap && wrap.querySelector('img');
            if (!img) return;
            if (img.complete && img.naturalWidth > 0) {
               applyScale(id);
            } else {
               img.addEventListener('load', () => applyScale(id), { once: true });
            }
         });
      });

      const pane = document.getElementById('tab-positions');
      if (pane && pane.classList.contains('show')) {
         ['b1', 'b2'].forEach(id => applyScale(id));
      }

      document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
         btn.addEventListener('shown.bs.tab', function () {
            const target = (this.dataset.bsTarget || '').replace('#tab-', '');
            const inp    = document.getElementById('active_tab_input');
            if (inp && target) inp.value = target;
         });
      });

      window.addEventListener('resize', () => {
         ['b1', 'b2'].forEach(id => applyScale(id));
      });

      // Re-activate server-specified tab after GLPI JS may have reset it
      if (cfg.activeTab && cfg.activeTab !== 'general') {
         setTimeout(() => {
            const trigger = document.querySelector('[data-bs-target="#tab-' + cfg.activeTab + '"]');
            if (trigger && window.bootstrap) {
               new bootstrap.Tab(trigger).show();
            }
         }, 0);
      }

      return true;
   }

   function observe() {
      if (init()) return;
      const obs = new MutationObserver(() => { if (init()) obs.disconnect(); });
      obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
      let n = 0;
      const t = setInterval(() => { if (init() || ++n > 75) clearInterval(t); }, 400);
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', observe);
   } else {
      observe();
   }
})();
