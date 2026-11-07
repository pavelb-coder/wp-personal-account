(function () {
  'use strict';
  function init() {
    var badge = document.querySelector('[data-wppa-unread-badge]');
    if (!badge || !window.fetch || !window.URLSearchParams) return;
    var busy = false;
    function refresh() {
      if (document.hidden || busy) return;
      busy = true;
      var body = new URLSearchParams();
      body.set('action', 'wppa_chat_unread');
      body.set('nonce', badge.dataset.nonce);
      fetch(badge.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body, cache: 'no-store' })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (!result || !result.success || !result.data) return;
          var total = Math.max(0, Number(result.data.total) || 0);
          badge.textContent = String(total);
          badge.hidden = total === 0;
          badge.setAttribute('aria-label', 'Непрочитанных сообщений: ' + total);
          var list = document.querySelector('[data-wppa-unread-list]');
          if (list && typeof result.data.html === 'string' && list.innerHTML !== result.data.html) list.innerHTML = result.data.html;
        }).catch(function () { /* При ошибке оставляем последние видимые данные до следующего обновления. */ })
        .then(function () { busy = false; });
    }
    refresh();
    window.setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    document.addEventListener('wppa:chat-updated', refresh);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
