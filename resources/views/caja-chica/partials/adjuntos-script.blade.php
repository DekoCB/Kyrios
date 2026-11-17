{{-- Lógica de los campos de fotos (se incluye UNA vez, fuera de cualquier <template>, para que el script sí se ejecute) --}}
@once
<script>
document.addEventListener('alpine:init', () => {
    const LADO = 1600, CALIDAD = 0.82, MAX = 6;

    /* Reduce una foto a un JPG de hasta 1600 px; los PDF y los formatos que el navegador no abre pasan tal cual. */
    async function reducir(f) {
        if (!f.type.startsWith('image/') || /hei[cf]/i.test(f.type + f.name)) return f;
        const url = URL.createObjectURL(f);
        try {
            const img = await new Promise((ok, no) => { const i = new Image(); i.onload = () => ok(i); i.onerror = no; i.src = url; });
            const k = Math.min(1, LADO / Math.max(img.naturalWidth, img.naturalHeight));
            if (k === 1 && f.size < 600 * 1024) return f;
            const c = document.createElement('canvas');
            c.width = Math.max(1, Math.round(img.naturalWidth * k));
            c.height = Math.max(1, Math.round(img.naturalHeight * k));
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            const blob = await new Promise(ok => c.toBlob(ok, 'image/jpeg', CALIDAD));
            if (!blob || blob.size >= f.size) return f;
            return new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        } catch (e) {
            return f;
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    Alpine.data('adjuntosCampo', () => ({
        lista: [], aviso: '',
        async agregar(ev) {
            const input = ev.target;
            const nuevos = Array.from(input.files);
            input.value = '';
            if (!nuevos.length) return;
            this.aviso = '';
            this.$dispatch('adjuntos-ocupado', 1);
            try {
                for (const f of nuevos) {
                    if (this.lista.length >= MAX) { this.aviso = 'Máximo ' + MAX + ' archivos por comprobante.'; break; }
                    this.lista.push(await reducir(f));
                }
                this.sincronizar();
            } finally {
                this.$dispatch('adjuntos-ocupado', -1);
            }
        },
        quitar(i) { this.lista.splice(i, 1); this.aviso = ''; this.sincronizar(); },
        sincronizar() {
            const dt = new DataTransfer();
            this.lista.forEach(f => dt.items.add(Alpine.raw(f)));
            this.$refs.salida.files = dt.files;
        },
    }));
});
</script>
@endonce
