<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fotos - {{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php $max = \App\Models\ProductoImagen::MAX_POR_VARIANTE; @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Fotos del producto</h1>
                <p class="text-sm text-gray-600 mt-1">Edita e imprime las fotos de {{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }}</p>
            </div>
            <a href="{{ route('inventario.productos.show', $producto) }}" class="text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-2"></i>Volver al producto</a>
        </div>

        @if(session('success'))
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-600">Marca las fotos que quieres imprimir. La estrella indica la foto principal de la variante.</p>
            <button type="button" onclick="abrirModalFotos()" class="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 text-sm"><i class="fas fa-print mr-2"></i>Imprimir fotos</button>
        </div>

        <div class="space-y-5">
            @forelse($producto->variantes->where('estado', 'activo') as $variante)
                @php $imgs = $variante->imagenes; @endphp
                <div class="bg-white rounded-lg shadow-md overflow-hidden" data-variante="{{ $variante->id }}">
                    <div class="px-6 py-3 bg-gray-50 border-b flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-gray-900">{{ $variante->nombre_completo }}</span>
                            <span class="ml-2 text-xs font-mono text-gray-500">{{ $variante->codigo_kyrios ?? $variante->sku }}</span>
                        </div>
                        <span class="text-xs text-gray-500"><i class="fas fa-images mr-1"></i>{{ $imgs->count() }}/{{ $max }}</span>
                    </div>
                    <div class="p-5 flex flex-wrap gap-4">
                        @foreach($imgs as $img)
                            <div class="w-40">
                                <label class="block cursor-pointer">
                                    <div class="relative">
                                        <img src="{{ $img->url }}" alt="Foto {{ $img->orden }}" class="h-40 w-40 rounded-lg object-cover border-2 {{ $img->es_principal ? 'border-indigo-500' : 'border-gray-200' }}">
                                        @if($img->es_principal)<span class="absolute top-1 right-1 bg-indigo-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px]"><i class="fas fa-star"></i></span>@endif
                                    </div>
                                    <span class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="fotos_imprimir[]" value="{{ $img->id }}" data-url="{{ $img->url }}" data-nombre="{{ \App\Support\EtiquetaProducto::nombre($producto) }}" data-codigo="{{ $variante->codigo_kyrios ?? $variante->sku }}" data-detalle="{{ $variante->nombre_completo !== 'Variante base' ? $variante->nombre_completo : '' }}" class="w-4 h-4 rounded border-gray-300 text-blue-600">
                                        Imprimir
                                    </span>
                                </label>
                                <div class="mt-1 flex items-center gap-3 text-xs">
                                    @unless($img->es_principal)
                                        <form action="{{ route('inventario.productos.imagenes.principal', $img) }}" method="POST">@csrf<button class="text-indigo-700 hover:underline"><i class="fas fa-star mr-1"></i>Principal</button></form>
                                    @endunless
                                    <form action="{{ route('inventario.productos.imagenes.destroy', $img) }}" method="POST" data-confirm="¿Eliminar esta foto?">@csrf @method('DELETE')<button class="text-red-600 hover:underline"><i class="fas fa-trash mr-1"></i>Eliminar</button></form>
                                </div>
                            </div>
                        @endforeach
                        @if($imgs->count() < $max)
                            <form action="{{ route('inventario.productos.variantes.imagenes.store', $variante) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <label class="h-40 w-40 rounded-lg border-2 border-dashed border-gray-300 hover:border-indigo-400 text-gray-400 hover:text-indigo-500 flex flex-col items-center justify-center cursor-pointer text-sm text-center">
                                    <i class="fas fa-plus mb-1 text-xl"></i>Agregar fotos
                                    <input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden"
                                           onchange="if (this.files.length > {{ $max - $imgs->count() }}) { alert('Solo puede agregar {{ $max - $imgs->count() }} foto(s) más a esta variante.'); this.value = ''; return; } this.form.submit();">
                                </label>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-md p-8 text-center text-gray-500">Este producto no tiene variantes activas; las fotos se cargan por variante.</div>
            @endforelse

            @if($producto->imagen_url && $producto->imagenes->isEmpty())
                <div class="bg-white rounded-lg shadow-md p-5 flex items-center gap-4">
                    <img src="{{ $producto->imagen_url }}" alt="" class="h-24 w-24 rounded-lg object-cover border border-gray-200">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Foto anterior del producto</p>
                        <label class="mt-1 flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="fotos_imprimir[]" value="producto" data-url="{{ $producto->imagen_url }}" data-nombre="{{ \App\Support\EtiquetaProducto::nombre($producto) }}" data-codigo="{{ $producto->codigo }}" data-detalle="" class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            Imprimir
                        </label>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== MODAL DE IMPRESIÓN ===================== --}}
    <div id="modalFotos" class="fixed inset-0 z-50 items-center justify-center p-4" style="display:none">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="cerrarModalFotos()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2"><i class="fas fa-print text-blue-600"></i>Imprimir fotos</h3>
                <button type="button" onclick="cerrarModalFotos()" class="text-gray-400 hover:text-gray-600 text-lg"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5 space-y-5 max-h-[75vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="copias_fotos" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-copy mr-1 text-blue-500"></i>Copias por foto</label>
                        <input type="number" id="copias_fotos" value="1" min="1" max="100" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label for="tamano_foto" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-ruler-combined mr-1 text-blue-500"></i>Tamaño de la foto</label>
                        <select id="tamano_foto" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="small">Pequeña (4 × 4 cm)</option>
                            <option value="medium" selected>Mediana (6 × 6 cm)</option>
                            <option value="large">Grande (9 × 9 cm)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-table mr-1 text-blue-500"></i>Fotos por fila</label>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach([2 => '2 por fila', 3 => '3 por fila', 4 => '4 por fila'] as $n => $txt)
                            <label class="cursor-pointer border-2 {{ $n === 3 ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }} rounded-xl p-3 text-center hover:border-blue-300 transition diseno-foto">
                                <input type="radio" name="columnas_foto" value="{{ $n }}" class="hidden" {{ $n === 3 ? 'checked' : '' }}>
                                <p class="text-sm font-medium text-gray-700">{{ $txt }}</p>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-info-circle mr-1 text-blue-500"></i>Información bajo la foto</label>
                    <div class="space-y-2 bg-gray-50 p-3 rounded-lg">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="foto_nombre" checked class="w-4 h-4 rounded border-gray-300 text-blue-600"><span class="text-sm text-gray-700">Nombre del producto</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="foto_codigo" checked class="w-4 h-4 rounded border-gray-300 text-blue-600"><span class="text-sm text-gray-700">Código de la variante</span></label>
                    </div>
                </div>
                <div class="bg-blue-50 rounded-lg p-4 text-sm text-blue-800"><i class="fas fa-image mr-1"></i>Total de fotos a imprimir: <span id="totalFotos" class="font-bold text-blue-900">0</span></div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="cerrarModalFotos()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 text-sm">Cancelar</button>
                <button type="button" onclick="imprimirFotos()" class="px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 flex items-center gap-2 text-sm"><i class="fas fa-print"></i>Imprimir</button>
            </div>
        </div>
    </div>

    <script>
        const marcadas = () => Array.from(document.querySelectorAll('input[name="fotos_imprimir[]"]:checked'));

        function actualizarTotalFotos() {
            const copias = parseInt(document.getElementById('copias_fotos').value) || 1;
            document.getElementById('totalFotos').textContent = marcadas().length * copias;
        }

        function abrirModalFotos() {
            document.getElementById('modalFotos').style.display = 'flex';
            actualizarTotalFotos();
        }

        function cerrarModalFotos() {
            document.getElementById('modalFotos').style.display = 'none';
        }

        document.querySelectorAll('input[name="fotos_imprimir[]"]').forEach(el => el.addEventListener('change', actualizarTotalFotos));
        document.getElementById('copias_fotos').addEventListener('input', actualizarTotalFotos);
        document.querySelectorAll('.diseno-foto').forEach(card => {
            card.addEventListener('click', function () {
                document.querySelectorAll('.diseno-foto').forEach(c => { c.classList.remove('border-blue-500', 'bg-blue-50'); c.classList.add('border-gray-200'); });
                this.classList.add('border-blue-500', 'bg-blue-50');
                this.classList.remove('border-gray-200');
                this.querySelector('input[type=radio]').checked = true;
            });
        });

        const escapar = t => String(t ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        function imprimirFotos() {
            const elegidas = marcadas().map(cb => ({ url: cb.dataset.url, nombre: cb.dataset.nombre, codigo: cb.dataset.codigo, detalle: cb.dataset.detalle }));
            if (!elegidas.length) { alert('Marca al menos una foto.'); return; }

            const copias = parseInt(document.getElementById('copias_fotos').value) || 1;
            const cols = parseInt(document.querySelector('input[name="columnas_foto"]:checked')?.value) || 3;
            const lado = { small: '4cm', medium: '6cm', large: '9cm' }[document.getElementById('tamano_foto').value] ?? '6cm';
            const verNombre = document.getElementById('foto_nombre').checked;
            const verCodigo = document.getElementById('foto_codigo').checked;

            const fotos = [];
            elegidas.forEach(f => { for (let i = 0; i < copias; i++) fotos.push(f); });

            const html = fotos.map(f => `
                <div class="foto">
                    <img src="${escapar(f.url)}" alt="">
                    ${verNombre ? `<div class="nombre">${escapar(f.nombre)}${f.detalle ? ' — ' + escapar(f.detalle) : ''}</div>` : ''}
                    ${verCodigo ? `<div class="codigo">${escapar(f.codigo)}</div>` : ''}
                </div>`).join('');

            const win = window.open('', '_blank', 'width=900,height=700');
            if (!win) { alert('El navegador bloqueó la ventana de impresión. Permite las ventanas emergentes.'); return; }
            win.document.write(`<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Fotos</title><style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #fff; }
                .rejilla { display: grid; grid-template-columns: repeat(${cols}, ${lado}); gap: 6mm; padding: 6mm; justify-content: start; }
                .foto { width: ${lado}; page-break-inside: avoid; text-align: center; }
                .foto img { width: ${lado}; height: ${lado}; object-fit: contain; border: 1px solid #ddd; }
                .nombre { font-size: 8pt; font-weight: bold; margin-top: 2px; }
                .codigo { font-size: 7pt; font-family: monospace; color: #444; }
                @media print { @page { margin: 5mm; } }
            </style></head><body><div class="rejilla">${html}</div></body></html>`);
            win.document.close();

            // Imprime cuando todas las fotos terminaron de cargar
            const esperar = () => {
                const imgs = Array.from(win.document.images);
                if (imgs.every(i => i.complete)) { win.focus(); win.print(); } else { setTimeout(esperar, 150); }
            };
            esperar();
        }
    </script>
</body>
</html>
