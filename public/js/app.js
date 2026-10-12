/**
 * Клиентская логика «Учёт ОКС»: быстрые действия без перезагрузки страницы.
 * Все запросы идут на /objects?a=... с CSRF-токеном из <meta name="csrf-token">.
 */
(function () {
  'use strict';

  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var base = document.body.dataset.base || '';

  function post(url, data) {
    var body = new URLSearchParams(data);
    body.append('csrf', token);
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  /* ---- Статус: клик по бейджу — переключение по кругу ---- */
  var STATUS_ORDER = ['none', 'work', 'paused', 'done', 'cancel'];
  var STATUS_META = {
    none:   ['Не назначен',   '#9e9e9e'],
    work:   ['В работе',      '#fb8c00'],
    paused: ['Приостановлен', '#1e88e5'],
    done:   ['Выполнен',      '#43a047'],
    cancel: ['Отменён',       '#e53935']
  };

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.status-badge');
    if (!btn || btn.disabled) return;
    ev.preventDefault();
    var id = btn.dataset.id;
    var cur = null;
    for (var k in STATUS_META) { if (STATUS_META[k][0] === btn.textContent.trim()) cur = k; }
    var next = STATUS_ORDER[((cur === null ? 0 : STATUS_ORDER.indexOf(cur)) + 1) % STATUS_ORDER.length];
    // Если прав на смену статуса нет — сервер ответит ошибкой, откатим внешний вид
    var oldText = btn.textContent, oldBg = btn.style.background;
    btn.textContent = STATUS_META[next][0];
    btn.style.background = STATUS_META[next][1];
    post(base + '/objects?a=status', { id: id, status: next }).then(function (res) {
      if (!res.ok) throw new Error(res.error || 'Нет права менять статус');
    }).catch(function (err) {
      btn.textContent = oldText; btn.style.background = oldBg;
      alert(err.message);
    });
  });

  /* ---- Отметки ИД в ПТО / Акт о подключении ---- */
  document.addEventListener('change', function (ev) {
    var cb = ev.target.closest('.mark-toggle');
    if (!cb) return;
    post(base + '/objects?a=mark', { id: cb.dataset.id, kind: cb.dataset.kind })
      .then(function (res) {
        if (!res.ok) throw new Error(res.error || 'Недоступно');
        cb.title = res.on ? ('От ' + res.date) : 'Не отмечено';
        var hint = cb.closest('.mark-box');
        if (hint) {
          var muted = hint.querySelector('.muted');
          if (muted) muted.textContent = res.on ? ('от ' + res.date) : '—';
        }
      })
      .catch(function (err) {
        cb.checked = !cb.checked; // откат
        alert(err.message);
      });
  });

  /* ---- Вкладки карточки ---- */
  document.querySelectorAll('[data-tabs]').forEach(function (root) {
    root.addEventListener('click', function (ev) {
      var b = ev.target.closest('.tab-btn');
      if (!b) return;
      root.querySelectorAll('.tab-btn').forEach(function (x) { x.classList.remove('active'); });
      root.querySelectorAll('.tab-pane').forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active');
      var pane = document.getElementById(b.dataset.tab);
      if (pane) pane.classList.add('active');
    });
  });
})();
