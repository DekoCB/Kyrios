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
        agregar(ev) {
            const input = ev.target;
            const nuevos = Array.from(input.files);
            input.value = '';
            return this.procesar(nuevos);
        },
        /* Ctrl+V sobre el campo: toma las imágenes del portapapeles (un texto pegado no se toca). */
        pegar(ev) {
            const imgs = Array.from(ev.clipboardData?.items || []).filter(i => i.kind === 'file' && i.type.startsWith('image/')).map(i => i.getAsFile()).filter(Boolean);
            if (!imgs.length) return;
            ev.preventDefault();
            return this.procesar(imgs.map((f, n) => new File([f], 'captura-' + Date.now() + (n ? '-' + n : '') + '.' + (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg'), { type: f.type })));
        },
        /* Botón «Pegar imagen»: lee el portapapeles con permiso del navegador. */
        async pegarPortapapeles() {
            this.aviso = '';
            try {
                const items = await navigator.clipboard.read();
                const archivos = [];
                for (const it of items) {
                    const tipo = it.types.find(t => t.startsWith('image/'));
                    if (tipo) { const b = await it.getType(tipo); archivos.push(new File([b], 'captura-' + Date.now() + '-' + archivos.length + '.' + tipo.split('/')[1], { type: tipo })); }
                }
                if (!archivos.length) { this.aviso = 'No hay ninguna imagen copiada en el portapapeles.'; return; }
                await this.procesar(archivos);
            } catch (e) {
                this.aviso = 'El navegador no dejó leer el portapapeles. Haz clic en esta zona y presiona Ctrl+V.';
            }
        },
        async procesar(nuevos) {
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
