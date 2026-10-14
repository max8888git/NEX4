/**
 * My account page (every role).
 *
 * - Profile photo: picked from the computer, cropped to a centred square and
 *   shrunk to 256px, then saved on the server via McuProfile (profile.js).
 * - Password: everyone changes their own (auth.php change_password).
 * - Name / email are read-only for most roles; the University Registrar edits
 *   their own through api/users.php.
 * - Text size preference for this computer.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var root = doc.getElementById('maPage');
  if (!root || !global.McuProfile) return;

  var P = global.McuProfile;
  var MAX_BYTES = 8 * 1024 * 1024;
  var OUT = 256;

  function $(id) { return doc.getElementById(id); }

  function notice(text) {
    var el = doc.createElement('div');
    el.className = 'ui-notice';
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 3500);
  }

  function setError(id, msg) {
    var el = $(id);
    if (!el) return;
    el.textContent = msg || '';
    el.hidden = !msg;
  }

  function fieldError(input, msg) {
    setError(input.id + 'Error', msg);
    if (msg) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  }

  // ----- details -----
  function renderDetails(u) {
    $('maName').textContent = u.name || '—';
    $('maEmail').textContent = u.email || '—';
    $('maRole').textContent = u.roleLabel || '—';
    $('maCollege').textContent = u.collegeName || (u.role === 'dean' || u.role === 'program_chair' ? '—' : 'All colleges');
    var deptRow = $('maDeptRow');
    deptRow.hidden = u.role !== 'program_chair';
    $('maDept').textContent = u.departmentName || '—';
    $('maRemovePhoto').hidden = !P.photo();
  }
  P.onChange(renderDetails);

  // ----- photo -----
  var fileInput = $('maPhotoInput');

  function cropToSquare(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onerror = function () { reject(new Error('That file couldn’t be read. Try another photo.')); };
      reader.onload = function () {
        var img = new Image();
        img.onerror = function () { reject(new Error('That file isn’t a photo this browser can open. Try a JPG or PNG.')); };
        img.onload = function () {
          var side = Math.min(img.naturalWidth, img.naturalHeight);
          var canvas = doc.createElement('canvas');
          canvas.width = canvas.height = OUT;
          var ctx = canvas.getContext('2d');
          ctx.fillStyle = '#fff';
          ctx.fillRect(0, 0, OUT, OUT);
          ctx.imageSmoothingQuality = 'high';
          ctx.drawImage(img, (img.naturalWidth - side) / 2, (img.naturalHeight - side) / 2, side, side, 0, 0, OUT, OUT);
          resolve(canvas.toDataURL('image/jpeg', 0.86));
        };
        img.src = reader.result;
      };
      reader.readAsDataURL(file);
    });
  }

  fileInput.addEventListener('change', function () {
    var file = fileInput.files && fileInput.files[0];
    fileInput.value = '';
    setError('maPhotoError', '');
    if (!file) return;
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
      setError('maPhotoError', 'Choose a JPG, PNG, WebP, or GIF image.');
      return;
    }
    if (file.size > MAX_BYTES) {
      setError('maPhotoError', 'That photo is larger than 8 MB. Choose a smaller one.');
      return;
    }
    var label = fileInput.closest('label');
    if (label) label.setAttribute('aria-busy', 'true');
    cropToSquare(file)
      .then(function (dataUrl) { return P.setPhoto(dataUrl); })
      .then(function () {
        notice('Profile photo updated.');
      }, function (err) {
        setError('maPhotoError', (err && err.message) || 'The photo couldn’t be saved. Try again.');
      })
      .then(function () { if (label) label.removeAttribute('aria-busy'); });
  });

  $('maRemovePhoto').addEventListener('click', function () {
    var btn = $('maRemovePhoto');
    btn.disabled = true;
    setError('maPhotoError', '');
    P.setPhoto(null).then(function () {
      notice('Profile photo removed.');
      fileInput.focus();
    }, function (err) {
      setError('maPhotoError', (err && err.message) || 'The photo couldn’t be removed. Try again.');
    }).then(function () { btn.disabled = false; });
  });

  // ----- text size -----
  Array.prototype.forEach.call(doc.querySelectorAll('input[name="maTextSize"]'), function (r) {
    r.checked = r.value === P.textSize();
    r.addEventListener('change', function () {
      if (!r.checked) return;
      P.setTextSize(r.value);
      notice(r.value === 'large' ? 'Larger text turned on for this computer.' : 'Text size set back to default.');
    });
  });

  // ----- University Registrar: edit own details and password (api/users.php) -----
  function saveOwn(body, btn, errId) {
    var u = P.user();
    if (!u || !global.McuApi) return Promise.reject();
    var payload = {
      action: 'update',
      id: u.id,
      name: u.name,
      email: u.email,
      role: u.role,
      isActive: true,
    };
    for (var k in body) if (Object.prototype.hasOwnProperty.call(body, k)) payload[k] = body[k];
    btn.disabled = true;
    setError(errId, '');
    return global.McuApi.send('POST', 'users.php', payload)
      .catch(function (err) {
        setError(errId, (err && err.message) || 'That didn’t save. Try again.');
        throw err;
      })
      .then(function (res) {
        btn.disabled = false;
        return res;
      }, function (err) {
        btn.disabled = false;
        throw err;
      });
  }

  var detailsForm = $('maDetailsForm');
  if (detailsForm) {
    var editBtn = $('maEditDetails');
    var view = $('maDetailsView');
    var showForm = function (show) {
      detailsForm.hidden = !show;
      view.hidden = show;
      editBtn.hidden = show;
      if (show) {
        var u = P.user() || {};
        $('maEditName').value = u.name || '';
        $('maEditEmail').value = u.email || '';
        $('maEditConfirm').value = '';
        ['maEditName', 'maEditEmail', 'maEditConfirm'].forEach(function (id) { fieldError($(id), ''); });
        setError('maDetailsError', '');
        $('maEditName').focus();
      } else {
        editBtn.focus();
      }
    };
    editBtn.addEventListener('click', function () { showForm(true); });
    $('maCancelDetails').addEventListener('click', function () { showForm(false); });
    detailsForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = $('maEditName');
      var email = $('maEditEmail');
      var confirm = $('maEditConfirm');
      var ok = true;
      fieldError(name, name.value.trim() ? '' : (ok = false, 'Enter your full name.'));
      var em = email.value.trim();
      fieldError(email, !em ? (ok = false, 'Enter your email address.') : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em) ? '' : (ok = false, 'That doesn’t look like an email address.'));
      fieldError(confirm, confirm.value ? '' : (ok = false, 'Enter your current password to save.'));
      if (!ok) {
        var first = detailsForm.querySelector('[aria-invalid="true"]');
        if (first) first.focus();
        return;
      }
      saveOwn({ name: name.value.trim(), email: em, confirmPassword: confirm.value }, $('maSaveDetails'), 'maDetailsError')
        .then(function () {
          P.setUser({ name: name.value.trim(), email: em });
          showForm(false);
          notice('Your details were saved.');
        }, function () {});
    });
  }

  var pwForm = $('maPasswordForm');
  if (pwForm) {
    pwForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var cur = $('maPwCurrent');
      var pw = $('maPwNew');
      var pw2 = $('maPwConfirm');
      var ok = true;
      fieldError(cur, cur.value ? '' : (ok = false, 'Enter your current password.'));
      fieldError(pw, pw.value.length >= 5 ? '' : (ok = false, 'Use at least 5 characters.'));
      fieldError(pw2, pw.value && pw2.value !== pw.value ? (ok = false, 'The passwords don’t match.') : '');
      if (!ok) {
        var first = pwForm.querySelector('[aria-invalid="true"]');
        if (first) first.focus();
        return;
      }
      var btn = $('maSavePassword');
      btn.disabled = true;
      setError('maPasswordError', '');
      global.McuApi.send('POST', 'auth.php', {
        action: 'change_password',
        currentPassword: cur.value,
        newPassword: pw.value,
        newPasswordConfirm: pw2.value,
      })
        .then(function () {
          pwForm.reset();
          notice('Password changed. Use it the next time you log in.');
        }, function (err) {
          var msg = (err && err.message) || 'That didn’t save. Try again.';
          // put the server's message next to the field it's about
          if (/current password/i.test(msg)) { fieldError(cur, msg); cur.focus(); }
          else if (/different|at least/i.test(msg)) { fieldError(pw, msg); pw.focus(); }
          else if (/do not match/i.test(msg)) { fieldError(pw2, msg); pw2.focus(); }
          else setError('maPasswordError', msg);
        })
        .then(function () { btn.disabled = false; });
    });
  }

  Array.prototype.forEach.call(doc.querySelectorAll('[data-ma-reveal]'), function (b) {
    b.addEventListener('click', function () {
      var input = $(b.getAttribute('data-ma-reveal'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.textContent = show ? 'Hide' : 'Show';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });
  Array.prototype.forEach.call(root.querySelectorAll('input'), function (el) {
    el.addEventListener('input', function () { if (el.getAttribute('aria-invalid')) fieldError(el, ''); });
  });
})(typeof window !== 'undefined' ? window : this);
