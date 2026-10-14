/**
 * Talks to the PHP/MySQL API from any page depth.
 *
 * The API is the /api folder at the site root. The root is everything before
 * the role folder in the page URL, so pages still work if the site is served
 * from a subpath. (This script is served by Vite with a hashed name, so its
 * own src can't be used to find the root.)
 */
(function (global) {
  'use strict';

  if (global.McuApi) return;

  function apiBase() {
    var href = global.location.href.split(/[?#]/)[0];
    var match = href.match(/^(.*?\/)(?:admin|super-admin|viewing|viewing-with-approval|viewing-program-head)\//);
    var root = match ? match[1] : href.replace(/[^/]*$/, '');
    return root + 'api/';
  }

  var BASE = apiBase();
  var lastError = null;
  var configured = false;

  function looksLikePhpSource(text) {
    var raw = String(text || '');
    return (
      raw.indexOf('<?php') !== -1 ||
      raw.indexOf('pdo_from_config') !== -1 ||
      /json_ok\s*\(/.test(raw)
    );
  }

  function phpServerHint() {
    var path = '';
    try {
      path = String(global.location.pathname || '').replace(/\\/g, '/');
    } catch (e) {
      path = '';
    }
    var match = path.match(
      /\/(admin|super-admin|viewing|viewing-with-approval|viewing-program-head)\/[^/]+$/
    );
    if (match) {
      return 'http://localhost:8000' + match[0];
    }
    return 'http://localhost:8000/';
  }

  function apiFailureMessage(text, res) {
    var raw = String(text || '').replace(/^\uFEFF/, '');
    if (looksLikePhpSource(raw)) {
      return (
        'This page is not running through PHP, so MySQL cannot be used. Open ' +
        phpServerHint() +
        ' instead of opening the HTML file directly.'
      );
    }
    var trimmed = raw.trim();
    if (trimmed.charAt(0) === '<') {
      return 'The API did not return JSON (HTTP ' + (res ? res.status : '?') + ').';
    }
    if (trimmed.length > 180) {
      return 'The API did not return JSON.';
    }
    return trimmed || 'The API did not return JSON.';
  }

  // Several scripts on a page ask for the same thing (health.php four times,
  // auth.php three, requests.php twice), and the dev server answers one
  // request at a time. Share one in-flight GET between callers, and keep these
  // for the page until something is saved to the same endpoint.
  var inflight = {};
  var PAGE_CACHED = { 'health.php': true, 'auth.php': true, 'requests.php': true };
  var pageCache = {};

  function request(method, path, body) {
    if (method !== 'GET') {
      if (path === 'auth.php') pageCache = {};
      else delete pageCache[path];
      return send(method, path, body);
    }
    if (pageCache[path]) return pageCache[path];
    if (inflight[path]) return inflight[path];
    var p = send(method, path, body);
    inflight[path] = p;
    p.then(function () {
      if (PAGE_CACHED[path]) pageCache[path] = p;
    }, function () {}).then(function () {
      delete inflight[path];
    });
    return p;
  }

  function send(method, path, body) {
    var opts = {
      method: method,
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    };
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return global.fetch(BASE + path, opts).then(function (res) {
      return res.text().then(function (text) {
        var data = {};
        try {
          data = text ? JSON.parse(text) : {};
        } catch (e) {
          throw new Error(apiFailureMessage(text, res));
        }
        if (!res.ok || data.ok === false) {
          throw new Error(data.error || apiFailureMessage(text, res) || 'Request failed (' + res.status + ')');
        }
        return data;
      });
    });
  }

  function health() {
    if (global.location && global.location.protocol === 'file:') {
      configured = false;
      lastError =
        'This page was opened as a file. MySQL is only available through PHP. Open ' +
        phpServerHint() +
        ' instead.';
      return Promise.resolve({ ok: false, configured: false, installed: false, error: lastError });
    }
    return request('GET', 'health.php')
      .then(function (data) {
        configured = !!(data.configured && data.installed);
        lastError = configured
          ? null
          : data.error ||
            (data.configured
              ? 'Database tables are missing. Open api/install.php.'
              : 'MySQL is not configured. Open api/install.php.');
        return data;
      })
      .catch(function (err) {
        configured = false;
        lastError = err.message || String(err);
        return { ok: false, configured: false, installed: false, error: lastError };
      });
  }

  global.McuApi = {
    base: BASE,
    get configured() {
      return configured;
    },
    get lastError() {
      return lastError;
    },
    health: health,
    get: function (path) {
      return request('GET', path);
    },
    send: function (method, path, body) {
      return request(method, path, body);
    }
  };
})(typeof window !== 'undefined' ? window : this);
