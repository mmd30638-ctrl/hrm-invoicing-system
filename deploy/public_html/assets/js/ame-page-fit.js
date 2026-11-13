(function () {
  var KEY = 'ame-page-fit:' + location.pathname;
  var SHOW = 'ame-page-fit-show';
  var root, editing = false;

  function snap(n) { return Math.round(n / 4) * 4; }

  function isRunningText(el) {
    if (!el || !el.closest) return false;
    return !!el.closest('#ameTickerBar, #ameRunDock, .ame-tbar, .ame-tbar-track, .ame-run-dock, .ame-run-line, .master-header-fixed');
  }

  function rootEl() {
    return document.getElementById('main-content') || document.body;
  }

  function visibleKids(node) {
    return Array.prototype.filter.call(node.children, function (el) {
      if (!el || el.nodeType !== 1) return false;
      if (el.classList.contains('ame-fit-ui') || el.classList.contains('ame-fit-move') || el.classList.contains('ame-fit-size') || el.classList.contains('ds-crumb')) return false;
      if (isRunningText(el)) return false;
      var tag = el.tagName;
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'LINK') return false;
      var box = el.getBoundingClientRect();
      return box.width >= 28 && box.height >= 14;
    });
  }

  function paintOf(el) {
    var bg = window.getComputedStyle(el).backgroundColor || '';
    var m = bg.match(/rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:[\s,]+([\d.]+))?\s*\)/i);
    if (!m) return null;
    if (m[4] !== undefined && Number(m[4]) === 0) return null;
    return { r: Number(m[1]), g: Number(m[2]), b: Number(m[3]) };
  }

  function isSheet(el) {
    var paint = paintOf(el);
    if (!paint) return false;
    return (paint.r + paint.g + paint.b) / 3 >= 214;
  }

  function markSheet(el) {
    if (!isSheet(el)) return;
    var box = el.getBoundingClientRect();
    if (box.width < 140 || box.height < 48) return;
    el.setAttribute('data-ame-sheet', '1');
  }

  function blocks() {
    var out = [];
    function walk(node) {
      var kids = visibleKids(node);
      if (!kids.length) return;
      if (kids.length === 1) {
        var only = kids[0];
        var onlyInner = visibleKids(only);
        var onlyTag = only.tagName;
        if (onlyInner.length && onlyTag !== 'FORM' && onlyTag !== 'TABLE' && onlyTag !== 'NAV' && onlyTag !== 'UL') {
          if (isSheet(only)) {
            markSheet(only);
            out.push(only);
          }
          walk(only);
          return;
        }
        markSheet(only);
        out.push(only);
        addBits(only);
        return;
      }
      kids.forEach(function (kid) {
        var tag = kid.tagName;
        var inner = visibleKids(kid);
        if (inner.length > 1 && tag !== 'FORM' && tag !== 'TABLE' && tag !== 'NAV' && tag !== 'UL') {
          if (isSheet(kid)) {
            markSheet(kid);
            out.push(kid);
          }
          walk(kid);
        } else {
          markSheet(kid);
          out.push(kid);
          addBits(kid);
        }
      });
    }
    function addBits(el) {
      var host = el.getBoundingClientRect();
      var sheet = el.closest('[data-ame-sheet]');
      var limit = sheet ? sheet.getBoundingClientRect() : host;
      Array.prototype.forEach.call(el.querySelectorAll('span, strong, b, em, p, label'), function (bit) {
        if (!bit || bit.nodeType !== 1) return;
        if (bit.querySelector('span, p, div, table, input, button')) return;
        var text = (bit.textContent || '').replace(/\s+/g, '');
        if (!text) return;
        var rect = bit.getBoundingClientRect();
        if (rect.width < 18 || rect.height < 10) return;
        var outside = rect.right > host.right + 3 || rect.left < host.left - 3 || rect.right > limit.right + 3 || rect.left < limit.left - 3;
        if (outside) {
          bit.setAttribute('data-ame-bit', '1');
          out.push(bit);
        }
      });
    }
    walk(root);
    var seen = [];
    return out.filter(function (el) {
      if (!el || seen.indexOf(el) !== -1) return false;
      seen.push(el);
      return true;
    });
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

  function loosen(el) {
    var node = el;
    while (node && node !== document.body && node !== document.documentElement) {
      if (isRunningText(node) || node.id === 'main-content') break;
      node.setAttribute('data-ame-loosen', '1');
      node.style.maxWidth = 'none';
      node.style.overflow = 'visible';
      node = node.parentElement;
    }
  }

  function tickerShown(el) {
    if (!el) return false;
    var style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden') return false;
    return el.getBoundingClientRect().height >= 8;
  }

  function seatLine(el) {
    if (!el || el.getAttribute('data-ame-seated') === '1') return;
    el.removeAttribute('data-ame-run-hid');
    el.removeAttribute('data-ame-fit');
    el.removeAttribute('data-ame-sheet');
    el.removeAttribute('data-ame-loosen');
    el.style.setProperty('position', 'relative', 'important');
    el.style.setProperty('top', 'auto', 'important');
    el.style.setProperty('height', '40px', 'important');
    el.style.setProperty('min-height', '40px', 'important');
    el.style.setProperty('max-height', '40px', 'important');
    el.style.setProperty('line-height', '40px', 'important');
    el.style.setProperty('white-space', 'nowrap', 'important');
    el.style.setProperty('order', '-10', 'important');
    var header = document.querySelector('.master-header-fixed');
    if (header && el.parentNode !== header) header.insertBefore(el, header.firstChild);
    el.style.setProperty('transform', 'none', 'important');
    el.style.setProperty('margin', '0', 'important');
    el.style.setProperty('overflow', 'hidden', 'important');
    el.setAttribute('data-ame-seated', '1');
  }

  function keepTicker() {
    var bar = document.getElementById('ameTickerBar');
    var dock = document.getElementById('ameRunDock');
    if (bar) seatLine(bar);
    var track = bar ? bar.querySelector('.ame-tbar-track') : null;
    if (track) {
      var name = window.getComputedStyle(track).animationName;
      if (!name || name === 'none') track.style.animation = 'ame-tick-keep 18s linear infinite';
    }
    if (dock && !tickerShown(bar)) {
      seatLine(dock);
      var line = dock.querySelector('.ame-run-line');
      if (line && window.getComputedStyle(line).animationName === 'none' && !line.style.transform) {
        line.style.animation = 'ame-tick-keep 18s linear infinite';
      }
    } else if (dock && bar && dock.getAttribute('data-ame-dock-off') !== '1') {
      dock.setAttribute('data-ame-dock-off', '1');
      dock.style.setProperty('display', 'none', 'important');
    }
  }

  function releaseRunningText() {
    document.querySelectorAll('#ameTickerBar, #ameRunDock, .ame-tbar, .ame-tbar-track, .ame-run-line, .ame-run-dock').forEach(function (el) {
      el.removeAttribute('data-ame-fit');
      el.removeAttribute('data-ame-sheet');
      el.removeAttribute('data-ame-bit');
      el.removeAttribute('data-ame-loosen');
      el.removeAttribute('data-ame-run-hid');
    });
    if (document.body.getAttribute('data-ame-loosen') === '1') {
      document.body.style.overflow = '';
      document.body.style.maxWidth = '';
      document.body.removeAttribute('data-ame-loosen');
    }
    keepTicker();
  }

  function place(el, box) {
    if (isRunningText(el)) return;
    var parent = el.parentElement;
    if (parent && window.getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
    var bit = el.getAttribute('data-ame-bit') === '1';
    el.setAttribute('data-ame-fit', '1');
    el.style.position = 'absolute';
    el.style.left = snap(box.x) + 'px';
    el.style.top = snap(box.y) + 'px';
    el.style.width = Math.max(bit ? 36 : 96, snap(box.w)) + 'px';
    el.style.height = Math.max(bit ? 16 : 28, snap(box.h)) + 'px';
    el.style.maxWidth = 'none';
    el.style.margin = '0';
    el.style.boxSizing = 'border-box';
    el.style.zIndex = bit ? '6' : '2';
    if (el.getAttribute('data-ame-sheet') === '1') loosen(el);
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

  function drag(handle, el, mode) {
    handle.addEventListener('pointerdown', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var sx = ev.clientX;
      var sy = ev.clientY;
      var ox = parseFloat(el.style.left) || 0;
      var oy = parseFloat(el.style.top) || 0;
      var ow = parseFloat(el.style.width) || el.offsetWidth;
      var oh = parseFloat(el.style.height) || el.offsetHeight;
      var minW = el.getAttribute('data-ame-bit') === '1' ? 36 : 96;
      function move(e) {
        var dx = e.clientX - sx;
        var dy = e.clientY - sy;
        if (mode === 'move') {
          el.style.left = snap(ox + dx) + 'px';
          el.style.top = snap(oy + dy) + 'px';
        } else {
          el.style.maxWidth = 'none';
          loosen(el);
          if (mode === 'w') {
            var nw = Math.max(minW, snap(ow - dx));
            el.style.width = nw + 'px';
            el.style.left = snap(ox + (ow - nw)) + 'px';
          } else {
            el.style.width = Math.max(minW, snap(ow + dx)) + 'px';
          }
          if (mode === 'se') el.style.height = Math.max(28, snap(oh + dy)) + 'px';
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
      var sheet = el.getAttribute('data-ame-sheet') === '1';
      var move = document.createElement('button');
      move.type = 'button';
      move.className = 'ame-fit-move' + (sheet ? ' ame-fit-sheet-handle' : '');
      move.textContent = sheet ? 'সাদা অংশ' : 'সরান';
      var size = document.createElement('button');
      size.type = 'button';
      size.className = 'ame-fit-size';
      size.setAttribute('aria-label', 'ছোট বড়');
      size.textContent = '⤡';
      el.appendChild(move);
      el.appendChild(size);
      drag(move, el, 'move');
      drag(size, el, 'se');
      if (el.getAttribute('data-ame-bit') === '1') drag(el, el, 'move');
      if (sheet) {
        ['e', 'w'].forEach(function (dir) {
          var edge = document.createElement('button');
          edge.type = 'button';
          edge.className = 'ame-fit-size ame-fit-edge ame-fit-edge-' + dir;
          edge.setAttribute('aria-label', dir === 'e' ? 'ডানে বড়' : 'বামে বড়');
          edge.textContent = dir === 'e' ? '▶' : '◀';
          el.appendChild(edge);
          drag(edge, el, dir);
        });
      }
    });
  }

  function wipeStyle(el) {
    ['position', 'left', 'top', 'width', 'height', 'margin', 'zIndex', 'maxWidth', 'minWidth', 'minHeight', 'boxSizing', 'overflow'].forEach(function (key) {
      el.style[key] = '';
    });
    el.removeAttribute('data-ame-fit');
    el.removeAttribute('data-ame-sheet');
    el.removeAttribute('data-ame-bit');
    el.removeAttribute('data-ame-loosen');
  }

  function restoreOriginal() {
    handles(false);
    root.querySelectorAll('[data-ame-fit], [data-ame-sheet], [data-ame-bit], [data-ame-loosen]').forEach(wipeStyle);
    if (document.body.hasAttribute('data-ame-loosen')) wipeStyle(document.body);
    localStorage.removeItem(KEY);
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

  function toolOn() {
    return localStorage.getItem(SHOW) === '1';
  }

  function ui() {
    var bar = document.createElement('div');
    bar.className = 'ame-fit-bar ame-fit-ui';
    if (document.getElementById('amePageFitToggle')) bar.classList.add('is-center');
    bar.hidden = true;
    bar.innerHTML = '<strong>এই পেজ সাজান</strong><span><button type="button" class="ame-fit-reset">আগের মতো</button> <button type="button" class="ame-fit-lock">সেভ ও লক</button></span>';
    document.body.appendChild(bar);
    var launch = null;
    function setTool(on) {
      localStorage.setItem(SHOW, on ? '1' : '0');
      var toggle = document.getElementById('amePageFitToggle');
      if (toggle) {
        toggle.classList.toggle('is-on', on);
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        var label = toggle.querySelector('.ame-fit-label');
        if (label) {
          var en = /[A-Za-z]/.test(label.textContent || '');
          label.textContent = on ? (en ? 'Arrange on' : 'সাজানো চালু') : (en ? 'Arrange this page' : 'পেজ সাজান');
        }
      }
      if (launch) launch.textContent = on ? 'বন্ধ' : 'সাজাও';
      editing = on;
      document.body.classList.toggle('ame-fit-on', on);
      bar.hidden = !on;
      if (on) {
        freeze();
        handles(true);
      } else {
        handles(false);
      }
    }
    var toggle = document.getElementById('amePageFitToggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        setTool(!toolOn());
        var sidebar = document.getElementById('mySidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        if (document.body.style.overflow === 'hidden') document.body.style.overflow = '';
      });
    } else {
      launch = document.createElement('button');
      launch.type = 'button';
      launch.className = 'ame-fit-launch ame-fit-ui';
      launch.textContent = 'সাজাও';
      document.body.appendChild(launch);
      launch.addEventListener('click', function () {
        setTool(!toolOn());
      });
    }
    bar.querySelector('.ame-fit-lock').addEventListener('click', function () {
      var items = collect();
      remember(items);
      var btn = bar.querySelector('.ame-fit-lock');
      btn.disabled = true;
      api('POST', { path: location.pathname, items: items }).then(function (data) {
        btn.disabled = false;
        btn.textContent = data && data.ok ? 'সেভ হয়েছে' : 'সেভ ও লক';
      });
    });
    bar.querySelector('.ame-fit-reset').addEventListener('click', function () {
      var stay = editing;
      restoreOriginal();
      api('POST', { path: location.pathname, items: [], reset: true });
      if (stay) setTool(true);
    });
    return setTool;
  }

  function boot() {
    releaseRunningText();
    root = rootEl();
    if (!root) return;
    applySaved();
    var setTool = ui();
    if (toolOn()) setTool(true);
    api('GET').then(function (data) {
      if (data && data.ok && data.items && data.items.length && !editing) applyItems(data.items);
      keepTicker();
    });
    keepTicker();
    window.setTimeout(keepTicker, 800);
    window.addEventListener('pageshow', keepTicker);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
