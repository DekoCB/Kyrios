import '../css/app.css';
import './bootstrap';
import './dialogos';
import Alpine from 'alpinejs';
window.Alpine = Alpine;

// Dólares (principal) o soles para mostrar precios; el valor guardado está en soles y se convierte con el tipo de cambio vigente
Alpine.store('moneda', {
    modo: (() => { try { return localStorage.getItem('kyrios_moneda') === 'PEN' ? 'PEN' : 'USD'; } catch (e) { return 'USD'; } })(),
    tc: parseFloat(document.querySelector('meta[name=tipo-cambio]')?.content) || 3.75,
    init() { window.addEventListener('kyr-moneda', (e) => { this.modo = e.detail; }); },
    fijar(m) { this.modo = m; try { localStorage.setItem('kyrios_moneda', m); } catch (e) {} },
    usd(soles) { return ((Number(soles) || 0) / this.tc).toFixed(2); },
    fmt(soles) { const v = Number(soles) || 0; return this.modo === 'USD' ? 'US$ ' + (v / this.tc).toFixed(2) : 'S/ ' + v.toFixed(2); },
});
Alpine.start();
