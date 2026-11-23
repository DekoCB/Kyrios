/* Visor del libro 3D de los catálogos de temporada. Las hojas ya vienen armadas desde el servidor (HTML); aquí solo
   se pasan, se mide el tamaño, se cargan las fotos cercanas y se cambia el modo de precios. */
(function () {
  'use strict';

  function iniciar() {
    var libro = document.getElementById('cl-libro');
    if (!libro) return;
    var escena = document.getElementById('cl-escena');
    var hojas = [].slice.call(libro.querySelectorAll('.cl-hoja'));
    var M = hojas.length;                                  // la portada cuenta como una hoja
    var contenido = parseInt(libro.getAttribute('data-contenido'), 10) || 0;
    var spread = 0;
    var $ant = document.getElementById('cl-ant'), $sig = document.getElementById('cl-sig'), $estado = document.getElementById('cl-estado');

    function medir() {
      var w = Math.floor(Math.min(330, (escena.clientWidth - 40) / 2));
      w = Math.max(w, 130);
      escena.style.setProperty('--w', w);
      libro.style.setProperty('--w', w);
      return w;
    }

    function cargarFotos() {
      hojas.forEach(function (el, k) {
        if (Math.abs(k - spread) > 2) return;
        [].forEach.call(el.querySelectorAll('img[data-src]'), function (img) { img.src = img.getAttribute('data-src'); img.removeAttribute('data-src'); });
      });
    }

    function aplicar() {
      var w = parseFloat(libro.style.getPropertyValue('--w')) || 300;
      hojas.forEach(function (el, k) {
        var girada = k < spread;
        el.style.transform = girada ? 'rotateY(-180deg)' : 'rotateY(0deg)';
        el.style.zIndex = girada ? k + 1 : M - k + 1;
      });
      libro.style.transform = spread === 0 ? 'translateX(' + (-w / 2) + 'px)' : (spread >= M ? 'translateX(' + (w / 2) + 'px)' : 'translateX(0)');
      var izq = libro.querySelector('.cl-base.izq'), der = libro.querySelector('.cl-base.der');
      if (izq) izq.style.opacity = spread > 0 && spread < M ? 1 : 0;
      if (der) der.style.opacity = spread < M ? 1 : 0;
      var t;
      if (spread === 0) t = 'Portada';
      else if (spread >= M) t = 'Contraportada';
      else if (spread === 1) t = 'Índice · Página 1';
      else { var i = 2 * (spread - 1); t = 'Páginas ' + i + '–' + (i + 1) + ' de ' + contenido; }
      if ($estado) $estado.textContent = t;
      if ($ant) $ant.disabled = spread === 0;
      if ($sig) $sig.disabled = spread >= M;
      cargarFotos();
    }

    function ir(n) { spread = Math.max(0, Math.min(M, n)); aplicar(); }

    if ($ant) $ant.addEventListener('click', function () { ir(spread - 1); });
    if ($sig) $sig.addEventListener('click', function () { ir(spread + 1); });
    libro.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); ir(spread + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); ir(spread - 1); }
    });

    // Clic en una mitad del libro o arrastre horizontal para pasar la página; los puntos del índice saltan a la sección
    var x0 = null, movio = false;
    libro.addEventListener('pointerdown', function (e) { x0 = e.clientX; movio = false; });
    libro.addEventListener('pointermove', function (e) { if (x0 !== null && Math.abs(e.clientX - x0) > 8) movio = true; });
    libro.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var d = e.clientX - x0; x0 = null;
      if (movio && Math.abs(d) > 40) { ir(spread + (d < 0 ? 1 : -1)); return; }
      var li = e.target.closest && e.target.closest('[data-ir]');
      if (li) { var idx = parseInt(li.getAttribute('data-ir'), 10); ir(idx % 2 === 1 ? (idx + 1) / 2 : idx / 2 + 1); return; }
      if (spread === 0) { ir(1); return; }
      if (spread >= M) { ir(spread - 1); return; }
      var r = libro.getBoundingClientRect();
      ir(spread + (e.clientX - r.left > r.width / 2 ? 1 : -1));
    });

    // Precios en pantalla: sin precio, soles o dólares
    [].forEach.call(document.querySelectorAll('[data-precios-set]'), function (b) {
      b.addEventListener('click', function () {
        libro.setAttribute('data-precios', b.getAttribute('data-precios-set'));
        [].forEach.call(document.querySelectorAll('[data-precios-set]'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      });
    });

    medir(); libro.classList.add('sin-anim'); aplicar();
    requestAnimationFrame(function () { requestAnimationFrame(function () { libro.classList.remove('sin-anim'); }); });
    if (window.ResizeObserver) {
      new ResizeObserver(function () {
        var w = Math.max(Math.floor(Math.min(330, (escena.clientWidth - 40) / 2)), 130);
        if (w !== parseFloat(libro.style.getPropertyValue('--w'))) { libro.classList.add('sin-anim'); medir(); aplicar(); requestAnimationFrame(function () { libro.classList.remove('sin-anim'); }); }
      }).observe(escena);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
