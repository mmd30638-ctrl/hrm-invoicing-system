(function () {
    var pay = document.getElementById('amePosPay');
    if (!pay || pay.getAttribute('data-pay-dock') === '1') {
        return;
    }
    pay.setAttribute('data-pay-dock', '1');

    var english = document.documentElement.lang === 'en';
    var tab = document.createElement('button');
    tab.type = 'button';
    tab.id = 'amePayDockTab';
    tab.className = 'ame-pay-dock-tab';
    tab.setAttribute('aria-controls', 'amePosPay');

    function word(open) {
        if (open) {
            return english ? 'Hide' : 'বন্ধ';
        }
        return english ? 'Pay' : 'পেমেন্ট';
    }

    function paint(open) {
        pay.classList.toggle('is-open', open);
        document.body.classList.toggle('ame-pay-open', open);
        tab.setAttribute('aria-expanded', open ? 'true' : 'false');
        tab.setAttribute('aria-label', open
            ? (english ? 'Hide the payment panel' : 'পেমেন্ট প্যানেল লুকান')
            : (english ? 'Show the payment panel' : 'পেমেন্ট প্যানেল খুলুন'));
        var path = open ? 'M9 6l6 6-6 6' : 'M15 6l-6 6 6 6';
        tab.innerHTML = '<svg class="ame-pay-dock-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="'
            + path
            + '" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path></svg><span class="ame-pay-dock-word">'
            + word(open)
            + '</span>';
    }

    tab.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        paint(!pay.classList.contains('is-open'));
    });

    document.body.appendChild(tab);
    paint(false);

    document.addEventListener('click', function (event) {
        var jump = event.target.closest && event.target.closest('#amePosPayJump, a[href="#amePosPay"]');
        if (jump) {
            event.preventDefault();
            paint(true);
            return;
        }
        if (!pay.classList.contains('is-open')) {
            return;
        }
        if (pay.contains(event.target) || tab.contains(event.target)) {
            return;
        }
        paint(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && pay.classList.contains('is-open')) {
            paint(false);
        }
    });
})();
