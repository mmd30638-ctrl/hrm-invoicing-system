(function () {
    var button = document.getElementById('amePosCashPaid');
    if (!button || button.getAttribute('data-cash-paid') === '1') {
        return;
    }
    button.setAttribute('data-cash-paid', '1');

    var english = document.documentElement.lang === 'en';

    function money(value) {
        var parsed = parseFloat(String(value == null ? '' : value).replace(/[^\d.-]/g, ''));
        return isFinite(parsed) ? parsed : 0;
    }

    function cashOption(select) {
        var options = select.options;
        var fallback = -1;
        var i;
        for (i = 0; i < options.length; i++) {
            if (!options[i].value) {
                continue;
            }
            var label = (options[i].textContent || '').toLowerCase();
            if (label.indexOf('ক্যাশ') >= 0 || label.indexOf('cash') >= 0) {
                return options[i];
            }
            if (fallback < 0 && label.indexOf('নগদ') >= 0) {
                fallback = i;
            }
        }
        return fallback >= 0 ? options[fallback] : null;
    }

    function payableNow() {
        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        }
        var node = document.getElementById('net-payable');
        var grand = document.getElementById('deskGrand');
        var saleBill = node && node.dataset.saleBill != null && node.dataset.saleBill !== ''
            ? money(node.dataset.saleBill)
            : money(node ? node.textContent : (grand ? grand.textContent : ''));
        var exchange = typeof window.ameExchangeTotal === 'function'
            ? Math.max(0, money(window.ameExchangeTotal()))
            : 0;
        return Math.max(0, Math.round((saleBill - exchange) * 100) / 100);
    }

    function poke(field) {
        if (!field) {
            return;
        }
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function mirrorCash(method, paid) {
        var box = document.getElementById('posPaymentRows');
        if (!box) {
            return;
        }
        var rows = box.querySelectorAll('.pos-payment-row');
        Array.prototype.forEach.call(rows, function (row, index) {
            var account = row.querySelector('.pos-payment-account');
            var amount = row.querySelector('.pos-payment-amount');
            if (index === 0) {
                if (account && method) {
                    account.value = method.value;
                }
                if (amount && paid) {
                    amount.value = paid.value || '0';
                }
                return;
            }
            if (amount) {
                amount.value = '0';
            }
        });
    }

    function forceComplete() {
        var mode = document.getElementById('amePosSaleMode');
        if (mode) {
            mode.value = 'complete';
        }
        var draft = document.getElementById('amePosRecentDraft');
        var complete = document.getElementById('amePosRecentComplete');
        if (draft) {
            draft.classList.remove('is-active');
        }
        if (complete) {
            complete.classList.add('is-active');
        }
        var current = document.getElementById('amePosRecentCurrent');
        if (current && complete) {
            current.textContent = (complete.textContent || '').replace(/\s+/g, ' ').trim();
        }
        var memo = document.getElementById('amePosCompleteBtn');
        if (memo) {
            memo.classList.remove('is-draft');
        }
        if (typeof window.amePosSyncMemoButton === 'function') {
            window.amePosSyncMemoButton(false);
        }
    }

    function clearShare() {
        window.amePosCashShare = false;
        try {
            sessionStorage.removeItem('amePosCashShare');
        } catch (err) {}
    }

    function armShare() {
        window.amePosCashShare = true;
        try {
            sessionStorage.setItem('amePosCashShare', '1');
        } catch (err) {}
        if (window.amePosShareReplace) {
            return;
        }
        var native = window.location.replace;
        if (typeof native !== 'function') {
            return;
        }
        try {
            window.location.replace = function (url) {
                var next = String(url || '');
                if (window.amePosCashShare && next.indexOf('invoice.php') !== -1 && next.indexOf('share=1') === -1) {
                    next += (next.indexOf('?') === -1 ? '?' : '&') + 'share=1';
                    clearShare();
                }
                return native.call(window.location, next);
            };
            window.amePosShareReplace = true;
        } catch (err) {}
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        if (target.closest('#amePosCashPaid')) {
            return;
        }
        if (target.closest('#amePosDraftComplete, #amePosCompleteBtn')) {
            clearShare();
        }
    }, true);

    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var amount = payableNow();
        var method = document.getElementById('deskPayMethod');
        var paid = document.getElementById('deskPaid');
        var paid2 = document.getElementById('deskPaid2');
        if (amount > 0.009) {
            var cash = method ? cashOption(method) : null;
            if (!cash) {
                window.alert(english
                    ? 'No cash account is open. Add a cash account, then try again.'
                    : 'ক্যাশ অ্যাকাউন্ট পাওয়া যায়নি। ক্যাশ অ্যাকাউন্ট খুলে আবার চাপুন।');
                return;
            }
            method.value = cash.value;
            if (paid) {
                paid.value = amount.toFixed(2);
            }
            if (paid2) {
                paid2.value = '0';
            }
            mirrorCash(method, paid);
            poke(method);
            poke(paid);
            poke(paid2);
        }
        forceComplete();
        armShare();
        if (typeof window.submitFinalSale === 'function') {
            window.submitFinalSale();
        }
    });
})();
