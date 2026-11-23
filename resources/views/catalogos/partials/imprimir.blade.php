{{-- Pestaña «Imprimir»: PDF de todo el catálogo o de las páginas elegidas. Recibe $sel y $libro. --}}
@php
    $L = count($libro['paginas']);
    // Hojas imprimibles: portada, índice, las páginas (numeradas como en el libro) y contraportada
    $chips = ['Portada', 'Índice'];
    for ($i = 1; $i <= $L - 2; $i++) { $chips[] = (string) $i; }
    $chips[] = 'Contra';
@endphp
<form method="GET" action="{{ route('catalogos.pdf', $sel) }}" target="_blank" x-data="{ que: 'todo', elegidas: [] }" class="grid gap-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <p class="text-xs font-semibold text-gray-600 mb-2">Qué imprimir</p>
            <label class="flex items-center gap-2 text-sm mb-1.5"><input type="radio" x-model="que" value="todo" class="text-yellow-500"> Todo el catálogo ({{ $L + 1 }} hojas)</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="que" value="algunas" class="text-yellow-500"> Solo las páginas que elija</label>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-600 mb-2">Precios en el PDF</p>
            <div class="cl-seg" x-data="{ v: 'none' }" role="group" aria-label="Precios en el PDF">
                <input type="hidden" name="precios" :value="v">
                @foreach(['none' => 'Sin precio', 'pen' => 'Soles (S/)', 'usd' => 'Dólares (US$)'] as $k => $t)
                    <button type="button" @click="v = '{{ $k }}'" :aria-pressed="v === '{{ $k }}'">{{ $t }}</button>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-1">Puede imprimir con precios aunque en pantalla los oculte.</p>
        </div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-2" for="i-tam">Tamaño de hoja</label>
            <select id="i-tam" name="tam" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm text-sm"><option>A4</option><option>A5</option><option>Carta</option></select></div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-2" for="i-for">Formato</label>
            <select id="i-for" name="formato" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm text-sm"><option value="una">Una página por hoja</option><option value="libro">Doble página (como libro)</option></select></div>
    </div>

    <div x-show="que === 'algunas'" x-cloak>
        <p class="text-xs font-semibold text-gray-600 mb-2">Páginas a imprimir</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach($chips as $k => $nombre)
                <label class="cursor-pointer"><input type="checkbox" name="paginas[]" value="{{ $k }}" x-model="elegidas" :disabled="que !== 'algunas'" class="peer sr-only">
                    <span class="inline-block min-w-[40px] text-center font-mono text-xs font-semibold border border-gray-300 rounded-lg px-2.5 py-1.5 peer-checked:bg-[#3f3d37] peer-checked:text-white peer-checked:border-[#3f3d37] peer-focus-visible:ring-2 peer-focus-visible:ring-yellow-400">{{ $nombre }}</span></label>
            @endforeach
        </div>
        <div class="mt-2 flex gap-2">
            <button type="button" class="text-xs border border-gray-300 rounded-lg px-2.5 py-1 hover:bg-gray-50" @click="elegidas = {{ json_encode(array_map('strval', array_keys($chips))) }}">Marcar todas</button>
            <button type="button" class="text-xs border border-gray-300 rounded-lg px-2.5 py-1 hover:bg-gray-50" @click="elegidas = []">Quitar todas</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
        <span class="text-sm text-gray-700"><b class="font-mono" x-text="que === 'todo' ? {{ $L + 1 }} : elegidas.length"></b> hojas</span>
        <button class="bg-yellow-400 hover:bg-yellow-500 disabled:opacity-50 text-gray-900 font-semibold text-sm px-5 py-2 rounded-lg" :disabled="que === 'algunas' && elegidas.length === 0"><i class="fas fa-file-pdf mr-1"></i>Generar PDF</button>
    </div>
    <p class="text-xs text-gray-500">El PDF se abre en otra pestaña con el mismo diseño del libro. Los catálogos grandes pueden tardar unos segundos.</p>
</form>
