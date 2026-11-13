(function () {
  var KEY = 'ame-page-fit:' + location.pathname;
  var root, editing = false;

  function snap(n) { return Math.round(n / 4) * 4; }

  function rootEl() {
    return document.getElementById('main-content') || document.body;
  }

  function visibleKids(node) {
    return Array.prototype.filter.call(node.children, function (el) {
      if (!el || el.nodeType !== 1) return false;
      if (el.classList.contains('ame-fit-ui') || el.classList.contains('ame-fit-move') || el.classList.contains('ame-fit-size') || el.classList.contains('ds-crumb')) return false;
      var tag = el.tagName;
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'LINK') return false;
      var box = el.getBoundingClientRect();
      return box.width >= 40 && box.height >= 24;
    });
  }

  function blocks() {
    function pick(node) {
      var kids = visibleKids(node);
      if (!kids.length) return [];
      if (kids.length === 1) {
        var deeper = pick(kids[0]);
        return deeper.length ? deeper : kids;
      }
      var out = [];
      kids.forEach(function (kid) {
        var tag = kid.tagName;
        var inner = visibleKids(kid);
        if (inner.length > 1 && tag !== 'FORM' && tag !== 'TABLE' && tag !== 'NAV' && tag !== 'UL') {
          out = out.concat(pick(kid));
        } else {
          out.push(kid);
        }
      });
      return out;
    }
    return pick(root);
  }

  function pathOf(el) {
    var parts = [];
    var node = el;
    while (node && node !== root) {
      var parent = node.parentElement;
      if (!parent) break;
      var i = 1;
      var sib = node;
      while ((sib = sib.previousElementSibling)) i += 1;
      parts.unshift(node.tagName.toLowerCase() + ':nth-child(' + i + ')');
      node = parent;
    }
    return parts.join('>');
  }

  function lookup(path) {
    var node = root;
    var bits = path.split('>');
    var i;
    for (i = 0; i < bits.length; i += 1) {
      var m = bits[i].match(/^([a-z0-9]+):nth-child\((\d+)\)$/i);
      if (!m) return null;
      var child = node.children[Number(m[2]) - 1];
      if (!child || child.tagName.toLowerCase() !== m[1]) return null;
      node = child;
    }
    return node;
  }

  function place(el, box) {
    var parent = el.parentElement;
    if (parent && window.getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
    el.setAttribute('data-ame-fit', '1');
    el.style.position = 'absolute';
    el.style.left = snap(Math.max(0, box.x)) + 'px';
    el.style.top = snap(Math.max(0, box.y)) + 'px';
    el.style.width = Math.max(96, snap(box.w)) + 'px';
    el.style.height = Math.max(36, snap(box.h)) + 'px';
    el.style.margin = '0';
    el.style.boxSizing = 'border-box';
    el.style.zIndex = '2';
  }

  function grow() {
    var seen = [];
    root.querySelectorAll('[data-ame-fit="1"]').forEach(function (el) {
      var p = el.parentElement;
      if (!p || seen.indexOf(p) !== -1) return;
      seen.push(p);
      var bottom = 0;
      Array.prototype.forEach.call(p.children, function (kid) {
        if (kid.getAttribute('data-ame-fit') !== '1') return;
        var y = parseFloat(kid.style.top) || 0;
        var h = parseFloat(kid.style.height) || 0;
        if (y + h + 20 > bottom) bottom = y + h + 20;
      });
      p.style.minHeight = bottom + 'px';
    });
  }

  function readSaved() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); }
    catch (e) { return null; }
  }

  function applySaved() {
    var saved = readSaved();
    if (!saved || !saved.items) return;
    saved.items.forEach(function (item) {
      var el = lookup(item.path);
      if (el) place(el, item);
    });
    grow();
  }

  function freeze() {
    var list = blocks().filter(function (el) { return el.getAttribute('data-ame-fit') !== '1'; });
    var shots = list.map(function (el) {
      var rect = el.getBoundingClientRect();
      var parent = el.parentElement.getBoundingClientRect();
      return { el: el, x: rect.left - parent.left, y: rect.top - parent.top, w: rect.width, h: rect.height };
    });
    shots.forEach(function (shot) { place(shot.el, shot); });
    grow();
  }

  function drag(handle, el, resize) {
    handle.addEventListener('pointerdown', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var sx = ev.clientX;
      var sy = ev.clientY;
      var ox = parseFloat(el.style.left) || 0;
      var oy = parseFloat(el.style.top) || 0;
      var ow = parseFloat(el.style.width) || el.offsetWidth;
      var oh = parseFloat(el.style.height) || el.offsetHeight;
      function move(e) {
        if (resize) {
          el.style.width = Math.max(96, snap(ow + e.clientX - sx)) + 'px';
          el.style.height = Math.max(36, snap(oh + e.clientY - sy)) + 'px';
        } else {
          el.style.left = snap(Math.max(0, ox + e.clientX - sx)) + 'px';
          el.style.top = snap(Math.max(0, oy + e.clientY - sy)) + 'px';
        }
        grow();
      }
      function up() {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
      }
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', up);
    });
  }

  function handles(on) {
    root.querySelectorAll('.ame-fit-move, .ame-fit-size').forEach(function (el) { el.remove(); });
    if (!on) return;
    blocks().forEach(function (el) {
      if (el.getAttribute('data-ame-fit') !== '1') return;
      var move = document.createElement('button');
      move.type = 'button';
      move.className = 'ame-fit-move';
      move.textContent = 'সরান';
      var size = document.createElement('button');
      size.type = 'button';
      size.className = 'ame-fit-size';
      size.setAttribute('aria-label', 'ছোট বড়');
      size.textContent = '⤡';
      el.appendChild(move);
      el.appendChild(size);
      drag(move, el, false);
      drag(size, el, true);
    });
  }

  function collect() {
    var items = [];
    blocks().forEach(function (el) {
      if (el.getAttribute('data-ame-fit') !== '1') return;
      items.push({
        path: pathOf(el),
        x: parseFloat(el.style.left) || 0,
        y: parseFloat(el.style.top) || 0,
        w: parseFloat(el.style.width) || el.offsetWidth,
        h: parseFloat(el.style.height) || el.offsetHeight
      });
    });
    return items;
  }

  function remember(items) {
    localStorage.setItem(KEY, JSON.stringify({ items: items }));
  }

  function applyItems(items) {
    root.querySelectorAll('[data-ame-fit="1"]').forEach(function (el) {
      el.removeAttribute('data-ame-fit');
      el.style.position = '';
      el.style.left = '';
      el.style.top = '';
      el.style.width = '';
      el.style.height = '';
      el.style.margin = '';
      el.style.zIndex = '';
    });
    items.forEach(function (item) {
      var el = lookup(item.path);
      if (el) place(el, item);
    });
    grow();
    remember(items);
  }

  function api(method, body) {
    var cfg = window.AME_PAGE_FIT || {};
    if (!cfg.url) return Promise.resolve(null);
    var opt = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    var url = cfg.url;
    if (method === 'GET') url += '?path=' + encodeURIComponent(location.pathname);
    if (body) {
      opt.headers['Content-Type'] = 'application/json';
      if (cfg.csrf) opt.headers['X-CSRF-Token'] = cfg.csrf;
      opt.body = JSON.stringify(body);
      opt.keepalive = true;
    }
    return fetch(url, opt).then(function (res) { return res.json(); }).catch(function () { return null; });
  }

  function ui() {
    var launch = document.createElement('button');
    launch.type = 'button';
    launch.className = 'ame-fit-launch ame-fit-ui';
    launch.setAttribute('aria-label', 'এই পেজের জায়গা সাজান');
    var bar = document.createElement('div');
    bar.className = 'ame-fit-bar ame-fit-ui';
    bar.hidden = true;
    bar.innerHTML = '<strong>এই পেজ সাজান</strong><span><button type="button" class="ame-fit-reset">আগের মতো</button> <button type="button" class="ame-fit-lock">সেভ ও লক</button></span>';
    document.body.appendChild(launch);
    document.body.appendChild(bar);
    function paint() {
      launch.classList.toggle('is-locked', !!readSaved() && !editing);
      launch.textContent = editing ? 'বন্ধ' : 'সাজাও';
    }
    launch.addEventListener('click', function () {
      editing = !editing;
      document.body.classList.toggle('ame-fit-on', editing);
      bar.hidden = !editing;
      if (editing) {
        freeze();
        handles(true);
      } else {
        handles(false);
      }
      paint();
    });
    bar.querySelector('.ame-fit-lock').addEventListener('click', function () {
      var items = collect();
      remember(items);
      var btn = bar.querySelector('.ame-fit-lock');
      btn.disabled = true;
      api('POST', { path: location.pathname, items: items }).then(function (data) {
        btn.disabled = false;
        editing = false;
        document.body.classList.remove('ame-fit-on');
        bar.hidden = true;
        handles(false);
        launch.title = data && data.ok ? 'এই পেজের সাইজ সেভ হয়েছে' : 'এই ফোনে সাইজ আটকানো আছে';
        paint();
      });
    });
    bar.querySelector('.ame-fit-reset').addEventListener('click', function () {
      localStorage.removeItem(KEY);
      api('POST', { path: location.pathname, items: [], reset: true }).finally(function () {
        location.reload();
      });
    });
    paint();
  }

  function boot() {
    root = rootEl();
    if (!root) return;
    applySaved();
    ui();
    api('GET').then(function (data) {
      if (!data || !data.ok || !data.items || !data.items.length || editing) return;
      applyItems(data.items);
      var launch = document.querySelector('.ame-fit-launch');
      if (launch) launch.classList.toggle('is-locked', data.items.length > 0);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
