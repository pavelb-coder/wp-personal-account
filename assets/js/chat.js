(function () {
  'use strict';
  function init() {
    var select = document.querySelector('[data-wppa-chat-select]');
    if (select) select.addEventListener('change', function () { if (select.value) window.location.assign(select.value); });
    var history = document.querySelector('[data-wppa-chat-history]');
    var form = document.querySelector('[data-wppa-chat-form]');
    if (!history || !form || !window.fetch || !window.URLSearchParams) return;
    var input = form.querySelector('textarea');
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('.wppa-chat__status');
    var older = document.querySelector('[data-wppa-chat-older]');
    var firstPage = history.dataset.page === '1';
    var busy = false;
    history.scrollTop = history.scrollHeight;
    function request(action, message) {
      var body = new URLSearchParams();
      body.set('action', action);
      body.set('nonce', history.dataset.nonce);
      body.set('mentor_id', history.dataset.mentor);
      if (typeof message === 'string') body.set('message', message);
      return fetch(history.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body, cache: 'no-store' })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (!result || !result.success) throw new Error(result && result.data && result.data.message || 'Не удалось обновить переписку. Проверьте вход в аккаунт и обновите страницу.');
          return result.data;
        });
    }
    function render(data, force) {
      var atBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 80;
      if (history.innerHTML.trim() !== data.html.trim()) history.innerHTML = data.html;
      if (older) older.hidden = !data.more;
      if (force || atBottom) history.scrollTop = history.scrollHeight;
    }
    function refresh() {
      if (!firstPage || document.hidden || busy) return;
      busy = true;
      request('wppa_chat_read').then(function (data) {
        render(data, false);
        status.textContent = '';
      }).catch(function (err) { status.textContent = err.message; }).then(function () { busy = false; });
    }
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (busy) { status.textContent = 'Идёт обновление. Попробуйте отправить ещё раз.'; return; }
      if (!input.value.trim()) return;
      var message = input.value;
      busy = true;
      button.disabled = true;
      input.readOnly = true;
      status.textContent = 'Отправка…';
      request('wppa_chat_send', message).then(function (data) {
        input.value = '';
        if (!firstPage) { window.location.assign(form.action); return; }
        render(data, true);
        status.textContent = 'Сообщение отправлено.';
      }).catch(function (err) { status.textContent = err.message + ' Текст сохранён в поле.'; })
        .then(function () { busy = false; button.disabled = false; input.readOnly = false; });
    });
    window.setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
