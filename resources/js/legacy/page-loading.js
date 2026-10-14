/**
 * Page loading feedback for every app page.
 *
 * - A thin gold progress bar at the top while API requests are in flight.
 * - "Still loading…" after 8 seconds if the page's first data hasn't arrived.
 * - "Some information couldn't load" with Try again if a data request fails.
 *
 * Loaded in <head>, before api-client.js, so it can watch requests to /api/
 * by wrapping fetch. Background polling (notifications) doesn't show the bar.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var realFetch = global.fetch;
  if (!realFetch || global.__mcuPageLoading) return;
  global.__mcuPageLoading = true;

  var SLOW_MS = 8000;
  var QUIET = /\/api\/notifications\.php/;
  var active = 0;
  var bar = null;
  var finishTimer = null;
  var firstLoadDone = false;
  var slowTimer = null;
  var toast = null;

  function ensureBar() {
    if (bar || !doc.body) return bar;
    bar = doc.createElement('div');
    bar.className = 'page-progress';
    bar.setAttribute('role', 'progressbar');
    bar.setAttribute('aria-label', 'Loading');
    bar.innerHTML = '<span></span>';
    doc.body.appendChild(bar);
    return bar;
  }

  function start() {
    var b = ensureBar();
    if (!b) return;
    clearTimeout(finishTimer);
    b.classList.remove('is-done');
    b.classList.add('is-active');
  }

  function done() {
    if (!bar) return;
    bar.classList.add('is-done');
    finishTimer = setTimeout(function () {
      bar.classList.remove('is-active', 'is-done');
    }, 350);
  }

  function showToast(kind, text, withRetry) {
    if (!doc.body) return;
    if (!toast) {
      toast = doc.createElement('div');
      toast.className = 'page-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      doc.body.appendChild(toast);
    }
    toast.className = 'page-toast page-toast--' + kind + ' is-visible';
    toast.innerHTML = '<span class="page-toast-text"></span>';
    toast.querySelector('.page-toast-text').textContent = text;
    if (withRetry) {
      var retry = doc.createElement('button');
      retry.type = 'button';
      retry.className = 'page-toast-btn';
      retry.textContent = 'Try again';
      retry.addEventListener('click', function () {
        global.location.reload();
      });
      toast.appendChild(retry);
    }
    var close = doc.createElement('button');
    close.type = 'button';
    close.className = 'page-toast-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';
    close.addEventListener('click', hideToast);
    toast.appendChild(close);
  }

  function hideToast() {
    if (toast) toast.classList.remove('is-visible');
  }

  function firstLoadFinished() {
    if (firstLoadDone) return;
    firstLoadDone = true;
    clearTimeout(slowTimer);
    if (toast && toast.classList.contains('page-toast--slow')) hideToast();
  }

  global.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    if (url.indexOf('/api/') === -1 || QUIET.test(url)) {
      return realFetch.apply(this, arguments);
    }
    var method = String((init && init.method) || 'GET').toUpperCase();
    active++;
    start();
    return realFetch.apply(this, arguments).then(function (res) {
      if (res.status >= 500 && method === 'GET') failed();
      return res;
    }, function (err) {
      if (method === 'GET') failed();
      throw err;
    }).finally(function () {
      active = Math.max(0, active - 1);
      if (!active) {
        done();
        // the page's first batch of data is in once requests go quiet after load
        setTimeout(function () {
          if (!active && doc.readyState === 'complete') firstLoadFinished();
        }, 300);
      }
    });
  };

  function failed() {
    showToast('error', 'Some information couldn’t load. Check your connection, then try again.', true);
  }

  slowTimer = setTimeout(function () {
    if (!firstLoadDone && active) {
      showToast('slow', 'Still loading… This is taking longer than usual. If it doesn’t finish, refresh the page.', false);
    }
  }, SLOW_MS);
})(typeof window !== 'undefined' ? window : this);
