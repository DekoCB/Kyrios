{{-- Pestaña «Compartir»: enlace público, vencimiento, precios del enlace, QR y vistas. Recibe $sel, $qr, $stats, $puedeEditar, $inp. --}}
@php
    $dias = '';
    if ($sel->enlace_expira_at) {
        $restan = max(1, (int) now()->diffInDays($sel->enlace_expira_at, false));
        $dias = collect([7, 30, 90])->sortBy(fn ($d) => abs($d - $restan))->first();
    }
@endphp
<div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.4fr)_auto] gap-8">
    <form method="POST" action="{{ route('catalogos.enlace', $sel) }}" class="grid gap-4 content-start">
        @csrf @method('PUT')
        <div>
            <label class="inline-flex items-center gap-2 font-semibold text-sm"><input type="checkbox" name="enlace_activo" value="1" @checked($sel->enlace_activo) @disabled(!$puedeEditar) class="rounded border-gray-300 text-green-600 w-4 h-4"> Enlace público activo</label>
            <p class="text-xs text-gray-500 mt-1">Cualquier persona con el enlace puede ver el libro, sin iniciar sesión.</p>
        </div>

        <div x-data="{ copiado: '' }">
            <div class="flex flex-wrap gap-2">
                <input id="enlace-url" readonly value="{{ $sel->url_publica }}" aria-label="Enlace público" class="flex-1 min-w-[220px] font-mono text-sm rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 {{ $sel->enlaceDisponible() ? '' : 'opacity-50 line-through' }}">
                <button type="button" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-lg" @click="
                    const i = document.getElementById('enlace-url');
                    try { navigator.clipboard.writeText(i.value).then(() => copiado = 'Enlace copiado.', () => { i.select(); copiado = 'Enlace seleccionado: pulse Ctrl+C para copiarlo.'; }); } catch (e) { i.select(); copiado = 'Enlace seleccionado: pulse Ctrl+C para copiarlo.'; }">Copiar enlace</button>
            </div>
            <p class="text-xs mt-1.5 {{ $sel->enlaceDisponible() ? 'text-gray-500' : 'text-amber-700' }}" x-text="copiado || @js($sel->enlaceDisponible() ? ($sel->enlace_expira_at ? 'El enlace vence el ' . $sel->enlace_expira_at->format('d/m/Y') . '.' : 'El enlace no vence.') : ($sel->enlace_activo ? 'El enlace venció: quien lo abra verá un aviso.' : 'El enlace está apagado: quien lo abra verá un aviso de que no está disponible.'))"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="{ v: '{{ $sel->precio_enlace }}' }">
            <div>
                <p class="text-xs font-semibold text-gray-600 mb-1.5">Precios en el enlace</p>
                <input type="hidden" name="precio_enlace" :value="v">
                <div class="cl-seg" role="group" aria-label="Precios en el enlace">
                    @foreach(['none' => 'Sin precio', 'pen' => 'Soles (S/)', 'usd' => 'Dólares (US$)'] as $k => $t)
                        <button type="button" @if($puedeEditar) @click="v = '{{ $k }}'" @endif :aria-pressed="v === '{{ $k }}'">{{ $t }}</button>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="s-dias">El enlace vence</label>
                <select id="s-dias" name="dias" @disabled(!$puedeEditar) class="{{ $inp }}">
                    @foreach(\App\Models\CatalogoLibro::VENCIMIENTOS as $v => $t)<option value="{{ $v }}" @selected((string) $dias === (string) $v)>{{ $t }}</option>@endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Al guardar, el plazo se cuenta desde hoy.</p>
            </div>
        </div>
        @if($puedeEditar)<div><button class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm px-5 py-2 rounded-lg">Guardar enlace</button></div>@endif
    </form>

    <div class="justify-self-center text-center">
        <div class="bg-white border border-gray-200 rounded-xl p-3 w-40 [&>svg]:w-full [&>svg]:h-auto {{ $sel->enlaceDisponible() ? '' : 'opacity-40' }}">{!! $qr !!}</div>
        <p class="text-xs text-gray-500 mt-2">Código QR del enlace</p>
    </div>
</div>

<div class="flex flex-wrap gap-8 mt-6 pt-5 border-t border-gray-200">
    <div><p class="font-mono text-2xl font-semibold text-gray-900">{{ $stats['abierto'] }}</p><p class="text-xs text-gray-500">veces abierto</p></div>
    <div><p class="font-mono text-2xl font-semibold text-gray-900">{{ $stats['personas'] }}</p><p class="text-xs text-gray-500">personas distintas</p></div>
</div>
<p class="text-xs text-gray-500 mt-3">Una persona que recarga la página dentro de 10 minutos no se cuenta dos veces. El enlace se puede apagar en cualquier momento.</p>
