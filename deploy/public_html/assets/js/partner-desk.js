(function () {
  'use strict';
  var page = document.querySelector('.pa-page');
  if (!page || page.getAttribute('data-ame-simple') === '1') return;
  page.setAttribute('data-ame-simple', '1');

  var hero = page.querySelector('.pa-hero');
  var cards = page.querySelector('.pa-cards');
  if (hero && cards) hero.insertAdjacentElement('afterend', cards);

  page.querySelectorAll('.pa-panel .sub').forEach(function (sub) {
    if (!sub.parentNode || sub.closest('details')) return;
    var details = document.createElement('details');
    details.className = 'pa-help';
    var summary = document.createElement('summary');
    summary.textContent = 'ব্যাখ্যা';
    details.appendChild(summary);
    sub.parentNode.insertBefore(details, sub);
    details.appendChild(sub);
  });

  var start = page.querySelector('.pa-start');
  if (start && start.parentNode === page) page.appendChild(start);

  var extraForm = null;
  page.querySelectorAll('form').forEach(function (node) {
    var action = node.querySelector('input[name="action"]');
    var type = node.querySelector('input[name="type"]');
    if (!extraForm && action && action.getAttribute('value') === 'save_capital' && type && type.getAttribute('value') === 'investment') {
      extraForm = node;
    }
  });
  if (extraForm && !page.querySelector('.pa-extra')) {
    var card = document.createElement('section');
    card.className = 'pa-extra pa-panel';
    var title = document.createElement('h2');
    title.textContent = 'অতিরিক্ত টাকা';
    var note = document.createElement('p');
    note.className = 'pa-extra-note';
    note.textContent = 'পার্টনার বাছুন, তারিখ দিন, টাকা লিখুন। গ্রাহক বা সাপ্লায়ারের হিসাব বদলায় না।';
    var partner = extraForm.querySelector('select[name="partner_id"]');
    var amount = extraForm.querySelector('input[name="amount"]');
    var when = extraForm.querySelector('input[name="transaction_date"]');
    var memo = extraForm.querySelector('input[name="note"]');
    var button = extraForm.querySelector('button[type="submit"]');
    if (partner) partner.setAttribute('aria-label', 'পার্টনার');
    if (amount) {
      amount.placeholder = 'টাকার পরিমাণ';
      amount.setAttribute('aria-label', 'টাকার পরিমাণ');
    }
    if (when) when.setAttribute('aria-label', 'তারিখ');
    if (memo) memo.placeholder = 'কারণ, ঐচ্ছিক';
    if (button) button.textContent = 'এই তারিখে টাকা যোগ করুন';
    extraForm.classList.add('pa-extra-form');
    card.appendChild(title);
    card.appendChild(note);
    card.appendChild(extraForm);
    var hero = page.querySelector('.pa-hero');
    if (hero && hero.parentNode === page) hero.insertAdjacentElement('afterend', card);
    else page.insertBefore(card, page.firstChild);
  }
})();
