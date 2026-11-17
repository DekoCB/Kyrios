{{-- Fotos o escaneos de un comprobante. Se pueden tomar o elegir varias, una por una; cada foto se reduce en el
     dispositivo antes de enviarse (un celular entrega fotos de 5 a 10 MB y la subida es lo que hace "colgar" el guardado).
     Variables: $nombre = expresión Alpine con el nombre del campo, ej. "'adjuntos[]'" o "'conceptos[' + i + '][adjuntos][]'" --}}
<div x-data="adjuntosCampo()" class="space-y-1">
    <div class="flex flex-wrap gap-2">
        <label class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-3 py-2 rounded-lg cursor-pointer">
            <i class="fas fa-camera"></i> Tomar foto
            <input type="file" accept="image/*" capture="environment" class="hidden" @change="agregar($event)">
        </label>
        <label class="inline-flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm font-semibold px-3 py-2 rounded-lg cursor-pointer">
            <i class="fas fa-file-upload"></i> Subir archivo / escaneo
            <input type="file" accept="image/*,application/pdf" multiple class="hidden" @change="agregar($event)">
        </label>
    </div>
    {{-- Aquí viajan los archivos reducidos --}}
    <input type="file" multiple class="hidden" x-ref="salida" :name="{{ $nombre }}">
    <ul class="flex flex-wrap gap-1 text-xs" x-show="lista.length" x-cloak>
        <template x-for="(f, i) in lista" :key="f.name + f.size + i">
            <li class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-700 px-2 py-1">
                <i class="fas" :class="f.type === 'application/pdf' ? 'fa-file-pdf text-red-500' : 'fa-image text-blue-500'"></i>
                <span class="max-w-[10rem] truncate" x-text="f.name"></span>
                <span class="text-gray-400" x-text="(f.size / 1024 < 1024 ? Math.round(f.size / 1024) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB')"></span>
                <button type="button" @click="quitar(i)" class="text-gray-400 hover:text-red-500" title="Quitar"><i class="fas fa-times"></i></button>
            </li>
        </template>
    </ul>
    <p class="text-xs text-red-600" x-show="aviso" x-text="aviso" x-cloak></p>
</div>
