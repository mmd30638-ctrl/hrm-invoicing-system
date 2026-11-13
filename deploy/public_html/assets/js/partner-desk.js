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
})();
