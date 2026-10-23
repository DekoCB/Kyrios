/**
 * Diálogos propios del sistema (reemplazan alert/confirm del navegador).
 *
 *  - kyrConfirm(mensaje, opciones) → Promise<boolean>
 *  - kyrAlert(mensaje, opciones)   → Promise<void>; window.alert se redirige aquí
 *  - Cualquier botón, enlace o formulario con data-confirm="mensaje" pide confirmación
 *    antes de continuar (sustituye a onclick/onsubmit="return confirm(...)").
 */
const PELIGRO = /elimin|borrar|anular|cancelar|quitar|reiniciar|desactivar|no se puede deshacer/i;
const ERROR = /^\s*error|no se pudo|no se puede|fall[óo]|inv[áa]lid|supera|debes|seleccion|ingrese|agregue|complete|falta/i;

let abierto = null;

function crear(html) {
    const t = document.createElement('div');
    t.innerHTML = html.trim();
    return t.firstElementChild;
}

function esc(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function mostrar({ titulo, mensaje, icono, tono, aceptar, cancelar }) {
    return new Promise((resolver) => {
        if (abierto) {
            abierto(false);
        }
        const previo = document.activeElement;
        const raiz = crear(`
            <div class="kyr-dlg" role="dialog" aria-modal="true" aria-labelledby="kyrDlgTitulo">
                <div class="kyr-dlg-caja kyr-dlg-${tono}">
                    <div class="kyr-dlg-cabeza">
                        <span class="kyr-dlg-icono"><i class="fas ${icono}"></i></span>
                        <h3 id="kyrDlgTitulo">${esc(titulo)}</h3>
                    </div>
                    <p class="kyr-dlg-texto">${esc(mensaje)}</p>
                    <div class="kyr-dlg-pie">
                        ${cancelar ? `<button type="button" class="kyr-dlg-btn kyr-dlg-cancelar">${esc(cancelar)}</button>` : ''}
                        <button type="button" class="kyr-dlg-btn kyr-dlg-aceptar">${esc(aceptar)}</button>
                    </div>
                </div>
            </div>`);

        const cerrar = (valor) => {
            document.removeEventListener('keydown', teclas, true);
            raiz.classList.add('kyr-dlg-saliendo');
            setTimeout(() => raiz.remove(), 120);
            abierto = null;
            if (previo && previo.focus) {
                previo.focus({ preventScroll: true });
            }
            resolver(valor);
        };
        const teclas = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                cerrar(false);
            } else if (e.key === 'Tab') {
                const btns = [...raiz.querySelectorAll('button')];
                const i = btns.indexOf(document.activeElement);
                e.preventDefault();
                btns[(i + (e.shiftKey ? -1 : 1) + btns.length) % btns.length].focus();
            }
        };

        abierto = cerrar;
        raiz.querySelector('.kyr-dlg-aceptar').addEventListener('click', () => cerrar(true));
        raiz.querySelector('.kyr-dlg-cancelar')?.addEventListener('click', () => cerrar(false));
        raiz.addEventListener('mousedown', (e) => {
            if (e.target === raiz) {
                cerrar(false);
            }
        });
        document.addEventListener('keydown', teclas, true);
        document.body.appendChild(raiz);

        // En acciones peligrosas el foco inicial va a "Cancelar" para evitar un Enter accidental.
        (tono === 'peligro' && cancelar ? raiz.querySelector('.kyr-dlg-cancelar') : raiz.querySelector('.kyr-dlg-aceptar')).focus();
    });
}

window.kyrConfirm = function (mensaje, op = {}) {
    const texto = String(mensaje ?? '').replace(/\\n/g, '\n');
    const peligro = op.peligro ?? PELIGRO.test(texto);

    return mostrar({
        titulo: op.titulo ?? (peligro ? 'Confirmar acción' : 'Confirmar'),
        mensaje: texto,
        icono: peligro ? 'fa-triangle-exclamation' : 'fa-circle-question',
        tono: peligro ? 'peligro' : 'normal',
        aceptar: op.aceptar ?? (peligro ? 'Sí, continuar' : 'Aceptar'),
        cancelar: op.cancelar ?? 'Cancelar',
    });
};

window.kyrAlert = function (mensaje, op = {}) {
    const texto = String(mensaje ?? '').replace(/\\n/g, '\n');
    const error = op.error ?? ERROR.test(texto);

    return mostrar({
        titulo: op.titulo ?? (error ? 'Revisa los datos' : 'Aviso'),
        mensaje: texto,
        icono: error ? 'fa-circle-exclamation' : 'fa-circle-info',
        tono: error ? 'error' : 'normal',
        aceptar: op.aceptar ?? 'Entendido',
        cancelar: null,
    });
};

// alert() del navegador → diálogo del sistema (no bloquea: el código sigue su curso).
window.alert = (mensaje) => { window.kyrAlert(mensaje); };

// ── data-confirm: botones, enlaces y formularios ──────────────────────────
function seguir(el, submitter) {
    if (el instanceof HTMLFormElement) {
        el.dataset.kyrOk = '1';
        el.requestSubmit(submitter && submitter.form === el ? submitter : undefined);
    } else if (el.tagName === 'A' && el.href) {
        el.target === '_blank' ? window.open(el.href, '_blank', 'noopener') : (window.location.href = el.href);
    } else if (el.form && (el.type === 'submit' || el.tagName === 'BUTTON')) {
        el.form.requestSubmit(el);
    } else {
        el.dataset.kyrOk = '1';
        el.click();
    }
}

function pedir(e, el, submitter) {
    e.preventDefault();
    e.stopImmediatePropagation();
    window.kyrConfirm(el.dataset.confirm, {
        titulo: el.dataset.confirmTitulo,
        aceptar: el.dataset.confirmAceptar,
        peligro: el.dataset.confirmPeligro === undefined ? undefined : el.dataset.confirmPeligro !== 'no',
    }).then((ok) => ok && seguir(el, submitter));
}

document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el || el instanceof HTMLFormElement) {
        return;
    }
    if (el.dataset.kyrOk) {
        delete el.dataset.kyrOk;
        return;
    }
    pedir(e, el);
}, true);

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
        return;
    }
    if (form.dataset.kyrOk) {
        delete form.dataset.kyrOk;
        return;
    }
    pedir(e, form, e.submitter);
}, true);
