<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Recepción con escáner - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Pantalla pensada para PDA / celular: estilos propios (no dependen del CSS compilado de Tailwind) */
        .rx { max-width: 760px; margin: 0 auto; padding-bottom: 96px; }
        .rx * { box-sizing: border-box; }
        .rx-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
        .rx-label { display: block; font-size: .78rem; font-weight: 600; color: #4b5563; margin-bottom: 4px; }
        .rx-input, .rx-select { width: 100%; padding: 12px 14px; font-size: 1rem; border: 2px solid #d1d5db; border-radius: 10px; background: #fff; }
        .rx-input:focus, .rx-select:focus { outline: none; border-color: #1e40af; }
        .rx-scan { font-size: 1.15rem; font-family: ui-monospace, Consolas, monospace; border-color: #1e40af; }
        .rx-row { display: flex; gap: 10px; align-items: center; }
        .rx-grow { flex: 1; min-width: 0; }
        .rx-btn { border: 0; border-radius: 10px; padding: 12px 16px; font-size: .95rem; font-weight: 600; cursor: pointer; background: #1e3a8a; color: #fff; }
        .rx-btn.sec { background: #e5e7eb; color: #111827; }
        .rx-btn.danger { background: #fee2e2; color: #991b1b; }
        .rx-btn:disabled { opacity: .5; cursor: not-allowed; }
        .rx-msg { border-radius: 10px; padding: 10px 14px; margin-bottom: 12px; font-size: .92rem; display: none; }
        .rx-msg.ok { display: block; background: #dcfce7; color: #166534; }
        .rx-msg.err { display: block; background: #fee2e2; color: #991b1b; }
        .rx-line { display: flex; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .rx-line:last-child { border-bottom: 0; }
        .rx-thumb { width: 52px; height: 52px; border-radius: 8px; object-fit: cover; background: #f3f4f6; flex: none; display: flex; align-items: center; justify-content: center; color: #9ca3af; }
        .rx-name { font-weight: 600; font-size: .95rem; line-height: 1.25; overflow-wrap: anywhere; }
        .rx-sub { font-size: .78rem; color: #6b7280; overflow-wrap: anywhere; }
        .rx-code { font-family: ui-monospace, Consolas, monospace; }
        .rx-qty { display: flex; align-items: center; gap: 6px; flex: none; }
        .rx-qty button { width: 38px; height: 38px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; font-size: 1.2rem; cursor: pointer; }
        .rx-qty input { width: 54px; text-align: center; padding: 8px 2px; font-size: 1rem; border: 1px solid #d1d5db; border-radius: 8px; }
        .rx-dano { display: inline-block; font-size: .68rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; background: #fef9c3; color: #854d0e; }
        .rx-bar { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: 10px 16px; z-index: 30; }
        .rx-bar-in { max-width: 760px; margin: 0 auto; display: flex; gap: 10px; align-items: center; }
        .rx-total { font-size: .85rem; color: #374151; flex: none; }
        .rx-pick { display: none; }
        .rx-pick.open { display: block; }
        .rx-item { display: flex; gap: 10px; align-items: center; width: 100%; text-align: left; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; margin-bottom: 8px; cursor: pointer; }
        .rx-item:hover { border-color: #1e40af; }
        .rx-title { font-weight: 700; font-size: .95rem; margin: 0 0 8px; }
        .rx-empty { text-align: center; color: #6b7280; padding: 22px 8px; font-size: .92rem; }
        @media (min-width: 768px) { .rx-bar { left: 16rem; } }
    </style>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Recepción con escáner" subtitle="Escanee las cajas para sumar mercadería al almacén sin digitar códigos" />

        <div class="rx">
            <div id="rxMsg" class="rx-msg" role="status" aria-live="polite"></div>

            <div class="rx-card">
                <div class="rx-row" style="flex-wrap: wrap;">
                    <div class="rx-grow" style="min-width: 200px;">
                        <label class="rx-label" for="rxAlmacen">Almacén que recibe</label>
                        <select id="rxAlmacen" class="rx-select">
                            <option value="">Seleccione…</option>
                            @foreach($almacenes as $a)
                                <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rx-grow" style="min-width: 200px;">
                        <label class="rx-label" for="rxRef">Guía / factura (opcional)</label>
                        <input id="rxRef" class="rx-input" type="text" maxlength="100" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="rx-card">
                <label class="rx-label" for="rxScan"><i class="fas fa-barcode"></i> Escanee un código</label>
                <div class="rx-row">
                    <input id="rxScan" class="rx-input rx-scan rx-grow" type="text" inputmode="none" autocomplete="off" autocapitalize="off" spellcheck="false"
                           placeholder="Apunte el lector a la caja…" autofocus>
                    <button type="button" id="rxKeyboard" class="rx-btn sec" title="Mostrar u ocultar el teclado en pantalla" aria-pressed="false"><i class="fas fa-keyboard"></i></button>
                </div>
                <p class="rx-sub" style="margin: 6px 0 0;">Acepta código Kyrios, del fabricante, del Excel anterior o de barras. El lector debe terminar con Enter.</p>
            </div>

            {{-- Código no registrado --}}
            <div id="rxUnknown" class="rx-card rx-pick">
                <p class="rx-title"><i class="fas fa-question-circle"></i> Código no registrado: <span id="rxUnknownCode" class="rx-code"></span></p>
                <p class="rx-sub" style="margin-bottom: 8px;">Busque el producto para asociarle este código (se hace una sola vez por caja) o cree un producto nuevo.</p>
                <input id="rxSearch" class="rx-input" type="text" placeholder="Nombre o código del producto…" autocomplete="off">
                <div id="rxSearchResults" style="margin-top: 10px;"></div>
                <div class="rx-row" style="margin-top: 6px; flex-wrap: wrap;">
                    <a id="rxCreate" class="rx-btn sec" style="text-decoration: none;" href="#"><i class="fas fa-plus"></i> Crear producto con este código</a>
                    <button type="button" id="rxUnknownClose" class="rx-btn sec">Descartar</button>
                </div>
            </div>

            {{-- Varias variantes con el mismo código --}}
            <div id="rxChoose" class="rx-card rx-pick">
                <p class="rx-title"><i class="fas fa-list"></i> Ese código corresponde a varias variantes. Elija una:</p>
                <div id="rxChooseList"></div>
                <button type="button" id="rxChooseClose" class="rx-btn sec">Cancelar</button>
            </div>

            <div class="rx-card">
                <p class="rx-title">Leídos <span id="rxCount" class="rx-sub"></span></p>
                <div id="rxLines"></div>
            </div>
        </div>
    </div>

    <div class="rx-bar">
        <div class="rx-bar-in">
            <button type="button" id="rxClear" class="rx-btn danger" title="Vaciar la lista"><i class="fas fa-trash"></i></button>
            <span id="rxTotal" class="rx-total">0 unidades</span>
            <button type="button" id="rxConfirm" class="rx-btn rx-grow" disabled><i class="fas fa-check"></i> Registrar ingreso</button>
        </div>
    </div>

<script>
(function () {
    var URLS = {
        buscar: @json(route('inventario.recepcion.buscar')),
        variantes: @json(route('inventario.recepcion.variantes')),
        asociar: @json(route('inventario.recepcion.asociar')),
        confirmar: @json(route('inventario.recepcion.confirmar')),
        crear: @json(route('inventario.productos.create'))
    };
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var STORE = 'kyrios_recepcion_v1';
    var $ = function (id) { return document.getElementById(id); };
    var scan = $('rxScan'), lines = [], pendingCode = '', audioCtx = null, busy = Promise.resolve();

    // ── Borrador en el navegador (sobrevive a una recarga o a un corte de Wi-Fi) ──
    function save() { try { localStorage.setItem(STORE, JSON.stringify({ lines: lines, almacen: $('rxAlmacen').value, ref: $('rxRef').value })); } catch (e) {} }
    function load() {
        try {
            var d = JSON.parse(localStorage.getItem(STORE) || 'null');
            if (d && Array.isArray(d.lines)) { lines = d.lines; $('rxAlmacen').value = d.almacen || ''; $('rxRef').value = d.ref || ''; }
        } catch (e) {}
    }

    // ── Avisos: mensaje, sonido y vibración ──
    function beep(ok) {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            var o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.frequency.value = ok ? 1100 : 220; g.gain.value = 0.12;
            o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(audioCtx.currentTime + (ok ? 0.09 : 0.35));
        } catch (e) {}
        try { if (navigator.vibrate) navigator.vibrate(ok ? 40 : [90, 60, 90]); } catch (e) {}
    }
    var msgTimer;
    function msg(text, kind) {
        var m = $('rxMsg'); m.className = 'rx-msg ' + kind; m.textContent = text;
        clearTimeout(msgTimer); if (kind === 'ok') msgTimer = setTimeout(function () { m.className = 'rx-msg'; }, 3500);
    }
    function focusScan() { if (!document.activeElement || !/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName) || document.activeElement === document.body) scan.focus(); }

    // ── Lista de lecturas ──
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function thumb(src) {
        if (src) { var i = el('img', 'rx-thumb'); i.src = src; i.alt = ''; return i; }
        var d = el('div', 'rx-thumb'); d.innerHTML = '<i class="fas fa-lightbulb"></i>'; return d;
    }
    function render() {
        var box = $('rxLines'); box.innerHTML = '';
        if (!lines.length) { box.appendChild(el('div', 'rx-empty', 'Aún no hay lecturas. Escanee la primera caja.')); }
        lines.forEach(function (l, idx) {
            var row = el('div', 'rx-line');
            row.appendChild(thumb(l.imagen));
            var info = el('div', 'rx-grow');
            info.appendChild(el('div', 'rx-name', l.nombre || 'Producto'));
            var sub = el('div', 'rx-sub'); sub.appendChild(el('span', 'rx-code', l.codigo_kyrios));
            if (l.detalle && l.detalle !== 'Variante base') sub.appendChild(document.createTextNode(' · ' + l.detalle));
            if (l.marca) sub.appendChild(document.createTextNode(' · ' + l.marca));
            info.appendChild(sub);
            if (l.estado_dano && l.estado_dano !== 'ninguno') info.appendChild(el('span', 'rx-dano', 'Daño ' + l.estado_dano));
            row.appendChild(info);

            var q = el('div', 'rx-qty');
            var minus = el('button', '', '−'); minus.type = 'button'; minus.setAttribute('aria-label', 'Restar');
            var inp = el('input'); inp.type = 'number'; inp.min = 1; inp.max = 100000; inp.value = l.cantidad; inp.inputMode = 'numeric';
            var plus = el('button', '', '+'); plus.type = 'button'; plus.setAttribute('aria-label', 'Sumar');
            var del = el('button', '', '×'); del.type = 'button'; del.setAttribute('aria-label', 'Quitar'); del.style.color = '#991b1b';
            minus.onclick = function () { l.cantidad = Math.max(1, l.cantidad - 1); update(); };
            plus.onclick = function () { l.cantidad += 1; update(); };
            inp.onchange = function () { l.cantidad = Math.max(1, Math.min(100000, parseInt(inp.value, 10) || 1)); update(); };
            del.onclick = function () { lines.splice(idx, 1); update(); };
            [minus, inp, plus, del].forEach(function (n) { q.appendChild(n); });
            row.appendChild(q);
            box.appendChild(row);
        });
        var total = lines.reduce(function (s, l) { return s + l.cantidad; }, 0);
        $('rxCount').textContent = lines.length ? '(' + lines.length + (lines.length === 1 ? ' variante)' : ' variantes)') : '';
        $('rxTotal').textContent = total + (total === 1 ? ' unidad' : ' unidades');
        $('rxConfirm').disabled = !lines.length;
    }
    function update() { save(); render(); }

    function addLine(v) {
        var found = lines.filter(function (l) { return l.id === v.id; })[0];
        if (found) { found.cantidad += 1; lines.splice(lines.indexOf(found), 1); lines.unshift(found); }
        else { v.cantidad = 1; lines.unshift(v); }
        update(); beep(true);
        msg('Leído: ' + (v.nombre || '') + ' · ' + v.codigo_kyrios, 'ok');
    }

    // ── Panel auxiliares ──
    function closePanels() { $('rxUnknown').classList.remove('open'); $('rxChoose').classList.remove('open'); $('rxSearchResults').innerHTML = ''; $('rxSearch').value = ''; }
    function itemButton(v, onclick) {
        var b = el('button', 'rx-item'); b.type = 'button';
        b.appendChild(thumb(v.imagen));
        var t = el('div', 'rx-grow'); t.appendChild(el('div', 'rx-name', v.nombre || 'Producto'));
        t.appendChild(el('div', 'rx-sub', v.codigo_kyrios + (v.detalle && v.detalle !== 'Variante base' ? ' · ' + v.detalle : '') + (v.marca ? ' · ' + v.marca : '')));
        b.appendChild(t); b.onclick = onclick; return b;
    }
    function showChoose(list) {
        var box = $('rxChooseList'); box.innerHTML = '';
        list.forEach(function (v) { box.appendChild(itemButton(v, function () { closePanels(); addLine(v); scan.focus(); })); });
        $('rxChoose').classList.add('open'); beep(true);
    }
    function showUnknown(code) {
        pendingCode = code; $('rxUnknownCode').textContent = code;
        $('rxCreate').href = URLS.crear + '?codigo_barras=' + encodeURIComponent(code);
        $('rxUnknown').classList.add('open'); beep(false);
        msg('El código ' + code + ' no está registrado.', 'err');
        $('rxSearch').focus();
    }

    function api(url, opts) {
        opts = opts || {}; opts.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
        return fetch(url, opts).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; }); });
    }

    function handleScan(raw) {
        var code = raw.replace(/[\x00-\x1F\x7F]+/g, '').trim();
        if (!code) return;
        closePanels();
        return api(URLS.buscar + '?codigo=' + encodeURIComponent(code)).then(function (r) {
            var res = r.resultados || [];
            if (res.length === 1) addLine(res[0]);
            else if (res.length > 1) showChoose(res);
            else showUnknown(code);
        }).catch(function () { beep(false); msg('Sin conexión con el servidor. Intente de nuevo.', 'err'); });
    }

    scan.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === 'Tab') {
            var v = scan.value; if (!v.trim()) { if (e.key === 'Enter') e.preventDefault(); return; }
            e.preventDefault(); scan.value = '';
            busy = busy.then(function () { return handleScan(v); }); // lecturas seguidas se procesan en orden
        }
    });
    $('rxKeyboard').onclick = function () {
        var on = scan.getAttribute('inputmode') === 'none';
        scan.setAttribute('inputmode', on ? 'text' : 'none'); this.setAttribute('aria-pressed', on ? 'true' : 'false');
        scan.blur(); scan.focus();
    };
    $('rxUnknownClose').onclick = function () { closePanels(); scan.focus(); };
    $('rxChooseClose').onclick = function () { closePanels(); scan.focus(); };

    // Asociar un código desconocido a una variante existente
    var st;
    $('rxSearch').addEventListener('input', function () {
        clearTimeout(st); var q = this.value.trim();
        st = setTimeout(function () {
            var box = $('rxSearchResults'); box.innerHTML = '';
            if (q.length < 2) return;
            api(URLS.variantes + '?q=' + encodeURIComponent(q)).then(function (list) {
                if (!Array.isArray(list) || !list.length) { box.appendChild(el('div', 'rx-sub', 'Sin resultados.')); return; }
                list.forEach(function (v) {
                    box.appendChild(itemButton(v, function () {
                        api(URLS.asociar, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ codigo: pendingCode, variante_id: v.id }) })
                            .then(function (r) {
                                if (r.ok) { closePanels(); addLine(v); msg('Código asociado a ' + v.codigo_kyrios + ' y leído.', 'ok'); scan.focus(); }
                                else { beep(false); msg(r.mensaje || 'No se pudo asociar el código.', 'err'); }
                            });
                    }));
                });
            });
        }, 250);
    });

    // Registrar el ingreso
    $('rxConfirm').onclick = function () {
        var almacen = $('rxAlmacen').value;
        if (!almacen) { beep(false); msg('Seleccione el almacén que recibe la mercadería.', 'err'); $('rxAlmacen').focus(); return; }
        var btn = this; btn.disabled = true;
        api(URLS.confirmar, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ almacen_id: almacen, documento_referencia: $('rxRef').value || null,
                lineas: lines.map(function (l) { return { variante_id: l.id, cantidad: l.cantidad }; }) })
        }).then(function (r) {
            if (r.ok) {
                var n = lines.reduce(function (s, l) { return s + l.cantidad; }, 0);
                lines = []; $('rxRef').value = ''; update(); beep(true);
                msg('Ingreso registrado: ' + n + ' unidades en ' + r.movimientos + ' variante(s).', 'ok');
            } else {
                beep(false); btn.disabled = false;
                var firstErr = r.errors ? Object.values(r.errors)[0][0] : null;
                msg(r.mensaje || firstErr || 'No se pudo registrar el ingreso.', 'err');
            }
            scan.focus();
        }).catch(function () { btn.disabled = false; beep(false); msg('Sin conexión. La lista queda guardada en este equipo.', 'err'); });
    };

    $('rxClear').onclick = function () {
        if (!lines.length) return;
        if (this.dataset.armed === '1') { lines = []; update(); this.dataset.armed = ''; this.innerHTML = '<i class="fas fa-trash"></i>'; scan.focus(); return; }
        this.dataset.armed = '1'; this.textContent = '¿Vaciar?'; var b = this;
        setTimeout(function () { b.dataset.armed = ''; b.innerHTML = '<i class="fas fa-trash"></i>'; }, 3000);
    };
    $('rxAlmacen').onchange = save; $('rxRef').onchange = save;

    // El lector necesita el foco: se recupera al tocar fuera de otros campos
    document.addEventListener('click', function (e) { if (!e.target.closest('input,select,textarea,button,a')) scan.focus(); });
    load(); render(); scan.focus();
})();
</script>
</body>
</html>
