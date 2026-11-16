/*
 * Keeps ThemeColorField's native picker and hex text box in sync. Uses
 * delegated listeners so it works for fields loaded into the CMS by ajax.
 */
(function () {
  var HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

  function parts(el) {
    var holder = el.closest('.sw-colorfield');
    if (!holder) return null;
    return {
      swatch: holder.querySelector('.sw-colorfield__swatch'),
      text: holder.querySelector('.sw-colorfield__text'),
    };
  }

  function toSixDigit(value) {
    var hex = value.trim().replace(/^#/, '');
    if (hex.length === 3) hex = hex.replace(/(.)/g, '$1$1');
    return '#' + hex.toLowerCase();
  }

  // The CMS's unsaved-changes tracking listens for change events on the
  // named (text) input, so changes made through the picker must fire one.
  function changed(input) {
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  document.addEventListener('input', function (event) {
    var p = parts(event.target);
    if (!p || !p.swatch || !p.text) return;

    if (event.target === p.swatch) {
      p.text.value = p.swatch.value;
    } else if (event.target === p.text && HEX.test(p.text.value.trim())) {
      p.swatch.value = toSixDigit(p.text.value);
    }
  });

  document.addEventListener('change', function (event) {
    var p = parts(event.target);
    if (p && event.target === p.swatch) changed(p.text);
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.sw-colorfield__reset');
    if (!button) return;
    var p = parts(button);
    if (!p || !p.text) return;
    p.text.value = '';
    if (p.swatch) p.swatch.value = button.getAttribute('data-default') || '#000000';
    changed(p.text);
  });
})();
