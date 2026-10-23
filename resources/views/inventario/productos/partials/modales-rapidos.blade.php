{{-- ============================================================
     MODALES DE CREACIÓN RÁPIDA – Marca / Modelo / Color
     Incluir al final del body en create.blade.php
     ============================================================ --}}

{{-- ── TOAST DE ÉXITO ─────────────────────────────────────── --}}
<div id="toastExito"
     class="fixed bottom-6 right-6 z-[9999] flex items-center gap-3
            bg-green-600 text-white px-5 py-3 rounded-xl shadow-2xl
            transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
    <i class="fas fa-check-circle text-lg"></i>
    <span id="toastMensaje"></span>
</div>

{{-- ── MODAL: NUEVA MARCA ──────────────────────────────────── --}}
<div id="modalMarca"
     class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modalMarcaTitulo">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         onclick="cerrarModalMarca()"></div>

    {{-- Contenido --}}
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md
                transform transition-all duration-200 scale-100">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 id="modalMarcaTitulo" class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-trademark text-blue-700 text-sm"></i>
                </span>
                Nueva Marca
            </h3>
            <button onclick="cerrarModalMarca()"
                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg p-1.5 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-4">
            <div>
                <label for="modalMarcaNombre" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Nombre de la Marca <span class="text-red-500">*</span>
                </label>
                <input type="text" id="modalMarcaNombre" autocomplete="off"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent
                              text-sm transition"
                       placeholder="Ej: Philips, Osram, Ledvance...">
            </div>

            <p id="modalMarcaError"
               class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            </p>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="cerrarModalMarca()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300
                           rounded-lg hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" id="btnGuardarMarca" onclick="guardarMarcaRapida()"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-lg
                           hover:bg-blue-800 transition flex items-center gap-1.5">
                <i class="fas fa-save"></i>
                Guardar
            </button>
        </div>
    </div>
</div>

{{-- ── MODAL: NUEVO MODELO ─────────────────────────────────── --}}
<div id="modalModelo"
     class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modalModeloTitulo">

    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         onclick="cerrarModalModelo()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 id="modalModeloTitulo" class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-mobile-alt text-indigo-700 text-sm"></i>
                </span>
                Nuevo Modelo
            </h3>
            <button onclick="cerrarModalModelo()"
                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg p-1.5 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            {{-- Marca actual (solo lectura) --}}
            <div class="flex items-center gap-2 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
                <i class="fas fa-trademark text-indigo-400 text-sm"></i>
                <span class="text-sm text-indigo-700">
                    Marca: <strong id="modalModeloMarcaNombre" class="font-semibold"></strong>
                </span>
            </div>

            <div>
                <label for="modalModeloNombre" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Nombre del Modelo <span class="text-red-500">*</span>
                </label>
                <input type="text" id="modalModeloNombre" autocomplete="off"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                              text-sm transition"
                       placeholder="Ej: Ring 30, Spot orientable">
            </div>

            <p id="modalModeloError"
               class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            </p>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="cerrarModalModelo()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300
                           rounded-lg hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" id="btnGuardarModelo" onclick="guardarModeloRapido()"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-700 rounded-lg
                           hover:bg-indigo-600 transition flex items-center gap-1.5">
                <i class="fas fa-save"></i>
                Guardar
            </button>
        </div>
    </div>
</div>

{{-- ── MODAL: NUEVO COLOR ──────────────────────────────────── --}}
<div id="modalColor"
     class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modalColorTitulo">

    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         onclick="cerrarModalColor()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 id="modalColorTitulo" class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="w-8 h-8 bg-pink-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-palette text-pink-600 text-sm"></i>
                </span>
                Nuevo Color
            </h3>
            <button onclick="cerrarModalColor()"
                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg p-1.5 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div>
                <label for="modalColorNombre" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Nombre del Color <span class="text-red-500">*</span>
                </label>
                <input type="text" id="modalColorNombre" autocomplete="off"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-pink-500 focus:border-transparent
                              text-sm transition"
                       placeholder="Ej: Azul Marino, Rojo Ferrari...">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Color (opcional)
                </label>
                <div class="flex items-center gap-3">
                    <input type="color" id="modalColorHex" value="#44413A"
                           class="h-11 w-14 rounded-lg cursor-pointer border-2 border-gray-200 p-0.5"
                           oninput="document.getElementById('modalColorPreview').style.backgroundColor = this.value">
                    <div id="modalColorPreview"
                         class="flex-1 h-11 rounded-lg border-2 border-gray-200 shadow-inner transition-colors"
                         style="background-color:#44413A;"></div>
                    <span id="modalColorHexLabel" class="text-xs font-mono text-gray-500 w-16">#44413A</span>
                </div>
            </div>

            <p id="modalColorError"
               class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            </p>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="cerrarModalColor()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300
                           rounded-lg hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" id="btnGuardarColor" onclick="guardarColorRapido()"
                    class="px-4 py-2 text-sm font-medium text-white bg-pink-600 rounded-lg
                           hover:bg-pink-500 transition flex items-center gap-1.5">
                <i class="fas fa-save"></i>
                Guardar
            </button>
        </div>
    </div>
</div>
{{-- ── MODAL: NUEVA UNIDAD DE MEDIDA ───────────────────────── --}}
<div id="modalUnidad"
     class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modalUnidadTitulo">

    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         onclick="cerrarModalUnidad()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 id="modalUnidadTitulo" class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-balance-scale text-green-600 text-sm"></i>
                </span>
                Nueva Unidad de Medida
            </h3>
            <button onclick="cerrarModalUnidad()"
                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg p-1.5 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Nombre <span class="text-red-500">*</span>
                </label>
                <input type="text" id="modalUnidadNombre" autocomplete="off"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-green-500 focus:border-transparent
                              text-sm transition"
                       placeholder="Ej: Kilogramo, Unidad, Caja">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Abreviatura <span class="text-red-500">*</span>
                </label>
                <input type="text" id="modalUnidadAbreviatura" autocomplete="off"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-green-500 focus:border-transparent
                              text-sm transition font-mono uppercase"
                       placeholder="Ej: KG, UND, CAJ"
                       maxlength="5"
                       oninput="this.value = this.value.toUpperCase()">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Tipo
                </label>
                <select id="modalUnidadTipo" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                               focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="unidad">Unidad</option>
                    <option value="masa">Masa</option>
                    <option value="volumen">Volumen</option>
                    <option value="longitud">Longitud</option>
                    <option value="empaque">Empaque</option>
                </select>
            </div>

            <p id="modalUnidadError"
               class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            </p>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="cerrarModalUnidad()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300
                           rounded-lg hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" id="btnGuardarUnidad" onclick="guardarUnidadRapida()"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg
                           hover:bg-green-500 transition flex items-center gap-1.5">
                <i class="fas fa-save"></i>
                Guardar
            </button>
        </div>
    </div>
</div>

{{-- ── JAVASCRIPT ──────────────────────────────────────────── --}}
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── TOAST ────────────────────────────────────────────────────
function mostrarToast(mensaje, tipo = 'exito') {
    const toast = document.getElementById('toastExito');
    document.getElementById('toastMensaje').textContent = mensaje;
    toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
    toast.classList.add('translate-y-0', 'opacity-100');
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
        toast.classList.remove('translate-y-0', 'opacity-100');
    }, 3000);
}

// ── HELPERS ──────────────────────────────────────────────────
function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); }
function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); }
function resetError(id)  { const el = document.getElementById(id); el.textContent = ''; el.classList.add('hidden'); }
function mostrarError(id, msg) { const el = document.getElementById(id); el.textContent = msg; el.classList.remove('hidden'); }
function setBtnLoading(id, loading) {
    const btn = document.getElementById(id);
    if (loading) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'; }
    else         { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Guardar'; }
}

async function peticion(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    return { res, data: await res.json() };
}

// ── MODAL MARCA ───────────────────────────────────────────────
function abrirModalMarca() {
    document.getElementById('modalMarcaNombre').value = '';
    resetError('modalMarcaError');
    abrirModal('modalMarca');
    setTimeout(() => document.getElementById('modalMarcaNombre').focus(), 50);
}
function cerrarModalMarca() { cerrarModal('modalMarca'); }

async function guardarMarcaRapida() {
    const nombre = document.getElementById('modalMarcaNombre').value.trim();
    const categoriaId = document.getElementById('categoria_id')?.value;

    if (!nombre) { mostrarError('modalMarcaError', 'El nombre es obligatorio.'); return; }

    if (!categoriaId) {
        mostrarError('modalMarcaError', 'Selecciona una categoría antes de crear una marca.');
        return;
    }
    
    setBtnLoading('btnGuardarMarca', true);
    resetError('modalMarcaError');

    try {

        const { res, data } = await peticion('{{ route("catalogo.marcas.rapida") }}', { 
            nombre: nombre,
            categoria_id: categoriaId 
        });
        if (res.ok && data.success) {
            // Agregar opción y seleccionarla en el select de marcas
            const sel = document.getElementById('marca_id');
            const opt = new Option(data.nombre, data.id, true, true);
            sel.add(opt);
            sel.value = data.id;
            sel.dispatchEvent(new Event('change')); // carga los modelos de esta marca
            cerrarModalMarca();
            mostrarToast(`Marca "${data.nombre}" creada exitosamente`);
        } else {
            const msg = data.errors?.nombre?.[0] ?? data.message ?? 'Error al crear la marca.';
            mostrarError('modalMarcaError', msg);
        }
    } catch {
        mostrarError('modalMarcaError', 'Error de conexión. Intenta de nuevo.');
    } finally {
        setBtnLoading('btnGuardarMarca', false);
    }
}

// ── MODAL MODELO ──────────────────────────────────────────────
function abrirModalModelo() {
    const marcaSel = document.getElementById('marca_id');
    if (!marcaSel.value) {
        mostrarToast('Selecciona una marca primero');
        return;
    }
    document.getElementById('modalModeloMarcaNombre').textContent =
        marcaSel.options[marcaSel.selectedIndex].text;
    document.getElementById('modalModeloNombre').value = '';
    resetError('modalModeloError');
    abrirModal('modalModelo');
    setTimeout(() => document.getElementById('modalModeloNombre').focus(), 50);
}
function cerrarModalModelo() { cerrarModal('modalModelo'); }

async function guardarModeloRapido() {
    const nombre  = document.getElementById('modalModeloNombre').value.trim();
    const marcaId = document.getElementById('marca_id').value;
    if (!nombre) { mostrarError('modalModeloError', 'El nombre es obligatorio.'); return; }

    setBtnLoading('btnGuardarModelo', true);
    resetError('modalModeloError');

    try {
        const { res, data } = await peticion('{{ route("catalogo.modelos.rapida") }}', { nombre, marca_id: marcaId });

        if (res.ok && data.success) {
            const sel = document.getElementById('modelo_id');
            const opt = new Option(data.nombre, data.id, true, true);
            sel.add(opt);
            sel.value     = data.id;
            sel.disabled  = false;
            cerrarModalModelo();
            mostrarToast(`Modelo "${data.nombre}" creado exitosamente`);
        } else {
            const msg = data.errors?.nombre?.[0] ?? data.message ?? 'Error al crear el modelo.';
            mostrarError('modalModeloError', msg);
        }
    } catch {
        mostrarError('modalModeloError', 'Error de conexión. Intenta de nuevo.');
    } finally {
        setBtnLoading('btnGuardarModelo', false);
    }
}

// ── MODAL COLOR ───────────────────────────────────────────────
function abrirModalColor() {
    document.getElementById('modalColorNombre').value = '';
    document.getElementById('modalColorHex').value    = '#44413A';
    document.getElementById('modalColorPreview').style.backgroundColor = '#44413A';
    document.getElementById('modalColorHexLabel').textContent = '#44413A';
    resetError('modalColorError');
    abrirModal('modalColor');
    setTimeout(() => document.getElementById('modalColorNombre').focus(), 50);
}
function cerrarModalColor() { cerrarModal('modalColor'); }

// Actualizar label hex en tiempo real
document.getElementById('modalColorHex')?.addEventListener('input', function () {
    document.getElementById('modalColorHexLabel').textContent = this.value;
});

async function guardarColorRapido() {
    const nombre     = document.getElementById('modalColorNombre').value.trim();
    const codigoHex  = document.getElementById('modalColorHex').value;
    if (!nombre) { mostrarError('modalColorError', 'El nombre es obligatorio.'); return; }

    setBtnLoading('btnGuardarColor', true);
    resetError('modalColorError');

    try {
        const { res, data } = await peticion('{{ route("catalogo.colores.rapida") }}', { nombre, codigo_hex: codigoHex });

        if (res.ok && data.success) {
            const sel = document.getElementById('color_id');
            const opt = new Option(data.nombre, data.id, true, true);
            sel.add(opt);
            sel.value = data.id;
            cerrarModalColor();
            mostrarToast(`Color "${data.nombre}" creado exitosamente`);
        } else {
            const msg = data.errors?.nombre?.[0] ?? data.message ?? 'Error al crear el color.';
            mostrarError('modalColorError', msg);
        }
    } catch {
        mostrarError('modalColorError', 'Error de conexión. Intenta de nuevo.');
    } finally {
        setBtnLoading('btnGuardarColor', false);
    }
}
// ── MODAL UNIDAD ─────────────────────────────────────────────
function abrirModalUnidad() {
    document.getElementById('modalUnidadNombre').value = '';
    document.getElementById('modalUnidadAbreviatura').value = '';
    document.getElementById('modalUnidadTipo').value = 'unidad';
    resetError('modalUnidadError');
    abrirModal('modalUnidad');
    setTimeout(() => document.getElementById('modalUnidadNombre').focus(), 50);
}

function cerrarModalUnidad() { 
    cerrarModal('modalUnidad'); 
}

async function guardarUnidadRapida() {
    const nombre = document.getElementById('modalUnidadNombre').value.trim();
    const abreviatura = document.getElementById('modalUnidadAbreviatura').value.trim();
    const tipo = document.getElementById('modalUnidadTipo').value;
    
    if (!nombre) { 
        mostrarError('modalUnidadError', 'El nombre es obligatorio.'); 
        return; 
    }
    
    if (!abreviatura) { 
        mostrarError('modalUnidadError', 'La abreviatura es obligatoria.'); 
        return; 
    }

    setBtnLoading('btnGuardarUnidad', true);
    resetError('modalUnidadError');

    try {
        const { res, data } = await peticion('{{ route("catalogo.unidades.rapida") }}', { 
            nombre, 
            abreviatura,
            tipo 
        });

        if (res.ok && data.success) {
            // Agregar opción a TODOS los selects de unidades
            const selects = [
                document.getElementById('unidad_base_id'),
                ...document.querySelectorAll('select[name*="unidad_id"]')
            ];
            
            selects.forEach(select => {
                if (select) {
                    const opt = new Option(`${data.nombre} (${data.abreviatura})`, data.id, false, false);
                    select.add(opt);
                }
            });
            
            cerrarModalUnidad();
            mostrarToast(`Unidad "${data.nombre}" creada exitosamente`);
        } else {
            const msg = data.errors?.nombre?.[0] ?? data.message ?? 'Error al crear la unidad.';
            mostrarError('modalUnidadError', msg);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('modalUnidadError', 'Error de conexión. Intenta de nuevo.');
    } finally {
        setBtnLoading('btnGuardarUnidad', false);
    }
}

// Funciones para unidades alternativas
let contadorUnidades = 0;

function agregarUnidadAlternativa() {
    const template  = document.getElementById('template-unidad-alternativa');
    const container = document.getElementById('unidades-alternativas-container');
    const msgDiv    = document.getElementById('sin-unidades-msg');
    const header    = document.getElementById('unidades-header');

    const clone = template.content.cloneNode(true);
    container.appendChild(clone);

    if (msgDiv)  msgDiv.style.display  = 'none';
    if (header)  header.style.display  = 'grid';
}

function eliminarUnidad(btn) {
    const item      = btn.closest('.unidad-item');
    const container = document.getElementById('unidades-alternativas-container');
    const msgDiv    = document.getElementById('sin-unidades-msg');
    const header    = document.getElementById('unidades-header');

    item.remove();

    if (container.children.length === 0) {
        if (msgDiv)  msgDiv.style.display  = 'block';
        if (header)  header.style.display  = 'none';
    }
}

// ── CERRAR CON ESCAPE ─────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('modalMarca').classList.contains('hidden'))   cerrarModalMarca();
    if (!document.getElementById('modalModelo').classList.contains('hidden'))  cerrarModalModelo();
    if (!document.getElementById('modalColor').classList.contains('hidden'))   cerrarModalColor();
    if (!document.getElementById('modalUnidad').classList.contains('hidden'))  cerrarModalUnidad();
});

// ── ENTER EN INPUTS ───────────────────────────────────────────
document.getElementById('modalMarcaNombre')?.addEventListener('keydown',  e => { if (e.key === 'Enter') guardarMarcaRapida(); });
document.getElementById('modalModeloNombre')?.addEventListener('keydown', e => { if (e.key === 'Enter') guardarModeloRapido(); });
document.getElementById('modalColorNombre')?.addEventListener('keydown',  e => { if (e.key === 'Enter') guardarColorRapido(); });
document.getElementById('modalUnidadNombre')?.addEventListener('keydown',  e => { if (e.key === 'Enter') guardarUnidadRapida(); });

</script>
