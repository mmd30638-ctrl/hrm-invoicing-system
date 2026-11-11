/* Sales desk: the payment column stays off the right edge until the arrow is pressed. */
(function () {
    if (window.__amePayDrawer) {
        return;
    }
    window.__amePayDrawer = true;

    var CSS = [
        'html body.ame-sales-pro, html body.ame-pos-terminal-page { --ame-pay-w: min(400px, 92vw); }',
        'html body.ame-sales-pro.ame-pos-terminal-page #main-content .ame-pos-page.ame-sale-pro.ame-sale-desk .ame-pos-shell .ame-pos-grid,',
        'html body.ame-pos-terminal-page #main-content .ame-sale-desk .ame-pos-shell .ame-pos-grid {',
        '  display: grid !important;',
        '  grid-template-columns: minmax(0, 1fr) !important;',
        '}',
        'html body #main-content .ame-sale-desk .ame-pos-shell #amePosPay.ame-pos-summary {',
        '  position: fixed !important;',
        '  top: 8px !important;',
        '  right: 0 !important;',
        '  bottom: 8px !important;',
        '  left: auto !important;',
        '  width: var(--ame-pay-w) !important;',
        '  max-width: var(--ame-pay-w) !important;',
        '  height: auto !important;',
        '  min-height: 0 !important;',
        '  max-height: none !important;',
        '  margin: 0 !important;',
        '  padding: 8px !important;',
        '  z-index: 1200 !important;',
        '  overflow-x: hidden !important;',
        '  overflow-y: auto !important;',
        '  -webkit-overflow-scrolling: touch;',
        '  transform: translateX(100%) !important;',
        '  transition: transform .22s ease !important;',
        '  background: #F4F7FB !important;',
        '  border-radius: 16px 0 0 16px !important;',
        '  box-shadow: none !important;',
        '  pointer-events: none !important;',
        '  grid-column: auto !important;',
        '  grid-row: auto !important;',
        '  box-sizing: border-box !important;',
        '}',
        'html body.is-pay-open #main-content .ame-sale-desk .ame-pos-shell #amePosPay.ame-pos-summary {',
        '  transform: translateX(0) !important;',
        '  box-shadow: -16px 0 40px rgba(15, 23, 42, .22) !important;',
        '  pointer-events: auto !important;',
        '}',
        'html body .ame-pay-tab {',
        '  position: fixed !important;',
        '  top: 50% !important;',
        '  right: 0 !important;',
        '  z-index: 1210 !important;',
        '  display: flex !important;',
        '  align-items: center !important;',
        '  justify-content: center !important;',
        '  width: 40px !important;',
        '  height: 88px !important;',
        '  margin: 0 !important;',
        '  padding: 0 !important;',
        '  border: 0 !important;',
        '  border-radius: 14px 0 0 14px !important;',
        '  background: #003F8F !important;',
        '  color: #ffffff !important;',
        '  box-shadow: -6px 0 18px rgba(15, 23, 42, .2) !important;',
        '  cursor: pointer !important;',
        '  transform: translateY(-50%) !important;',
        '  transition: right .22s ease !important;',
        '  touch-action: manipulation;',
        '}',
        'html body.is-pay-open .ame-pay-tab { right: var(--ame-pay-w) !important; }',
        'html body .ame-pay-tab svg { display: block; transition: transform .22s ease; }',
        'html body.is-pay-open .ame-pay-tab svg { transform: rotate(180deg); }',
        '@media print {',
        '  html body .ame-pay-tab { display: none !important; }',
        '  html body #main-content .ame-sale-desk #amePosPay.ame-pos-summary {',
        '    position: static !important;',
        '    transform: none !important;',
        '    width: auto !important;',
        '    max-width: none !important;',
        '    pointer-events: auto !important;',
        '    box-shadow: none !important;',
        '  }',
        '}'
    ].join('\n');

    var booted = false;

    function english() {
        return document.documentElement.lang === 'en';
    }

    function mountStyle() {
        var node = document.getElementById('amePayDrawerStyle');
        if (!node) {
            node = document.createElement('style');
            node.id = 'amePayDrawerStyle';
            node.textContent = CSS;
        }
        (document.body || document.documentElement).appendChild(node);
    }

    function panel() {
        return document.getElementById('amePosPay') || document.querySelector('aside.ame-pos-summary');
    }

    function setOpen(open) {
        var box = panel();
        var tab = document.getElementById('amePayTab');
        document.body.classList.toggle('is-pay-open', !!open);
        if (tab) {
            tab.setAttribute('aria-expanded', open ? 'true' : 'false');
            tab.setAttribute('aria-label', open
                ? (english() ? 'Hide payment' : 'পেমেন্ট লুকান')
                : (english() ? 'Show payment' : 'পেমেন্ট দেখান'));
        }
        if (box) {
            box.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (open) {
                box.removeAttribute('inert');
            } else {
                box.setAttribute('inert', '');
            }
        }
    }

    function boot() {
        mountStyle();
        if (!document.querySelector('.ame-sale-desk')) {
            return;
        }
        var box = panel();
        if (!box) {
            return;
        }
        if (booted) {
            return;
        }
        booted = true;
        var tab = document.createElement('button');
        tab.type = 'button';
        tab.id = 'amePayTab';
        tab.className = 'ame-pay-tab';
        tab.setAttribute('aria-controls', box.id || 'amePosPay');
        tab.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M14.5 5.5 8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        tab.addEventListener('click', function () {
            setOpen(!document.body.classList.contains('is-pay-open'));
        });
        document.body.appendChild(tab);
        setOpen(false);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.classList.contains('is-pay-open')) {
                setOpen(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
