(function () {
  if (navigator.doNotTrack === '1' || window.doNotTrack === '1') {
    return;
  }

  function uuid() {
    if (crypto.randomUUID) {
      return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
      var random = (Math.random() * 16) | 0;
      return (char === 'x' ? random : (random & 0x3) | 0x8).toString(16);
    });
  }

  // Nomes antigos (rba_*): o visitante mantém o mesmo identificador e as chaves velhas somem.
  var legacyVisitor = localStorage.getItem('rba_vid');
  if (legacyVisitor && !localStorage.getItem('qv_vid')) {
    localStorage.setItem('qv_vid', legacyVisitor);
  }
  localStorage.removeItem('rba_vid');
  sessionStorage.removeItem('rba_sid');
  sessionStorage.removeItem('rba_sid_at');

  var visitorKey = localStorage.getItem('qv_vid');
  if (!visitorKey) {
    visitorKey = uuid();
    localStorage.setItem('qv_vid', visitorKey);
  }

  var sessionKey = sessionStorage.getItem('qv_sid');
  var sessionAt = Number(sessionStorage.getItem('qv_sid_at') || 0);
  if (!sessionKey || Date.now() - sessionAt > 30 * 60 * 1000) {
    sessionKey = uuid();
    sessionStorage.setItem('qv_sid', sessionKey);
  }

  function touchSession() {
    sessionStorage.setItem('qv_sid_at', String(Date.now()));
  }

  touchSession();

  function loadTime() {
    var navigation = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    if (!navigation) {
      return 0;
    }

    if (navigation.duration) {
      return Math.round(navigation.duration);
    }

    if (navigation.loadEventEnd) {
      return Math.round(navigation.loadEventEnd);
    }

    return 0;
  }

  function send(type, extra) {
    var body = JSON.stringify(Object.assign({
      t: type,
      sid: sessionKey,
      vid: visitorKey,
      path: location.pathname,
      ref: document.referrer || '',
    }, extra || {}));

    if (type !== 'view' && navigator.sendBeacon) {
      navigator.sendBeacon('/t', new Blob([body], { type: 'application/json' }));
      return;
    }

    fetch('/t', {
      method: 'POST',
      body: body,
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'omit',
      keepalive: true,
    }).catch(function () {});
  }

  function sendView() {
    send('view', { lt: loadTime() });
    touchSession();
  }

  if (document.readyState === 'complete') {
    sendView();
  } else {
    window.addEventListener('load', function () {
      setTimeout(sendView, 0);
    });
  }

  setInterval(function () {
    if (document.visibilityState === 'hidden') {
      return;
    }

    send('beat');
    touchSession();
  }, 20000);

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      send('beat');
    }
  });

  window.addEventListener('pagehide', function () {
    send('leave');
  });
})();
