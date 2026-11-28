@extends('layouts.modulo')
@section('titulo', 'Evaluar a ' . $empleado->nombre_completo)
@section('subtitulo', $empleado->cargo . ($empleado->area ? ' · ' . $empleado->area : ''))

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'evaluaciones'])
    @php $caja = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring focus:ring-blue-200'; $etq = 'block text-xs font-medium text-gray-600 mb-1'; $sueldo = (float) $empleado->sueldo_basico; @endphp

    <a href="{{ route('rrhh.evaluaciones.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 mb-4"><i class="fas fa-arrow-left"></i> Todas las evaluaciones</a>

    <div class="grid lg:grid-cols-3 gap-6">
        <section class="lg:col-span-1 bg-white rounded-xl shadow-sm p-5 self-start" x-data="{ aumento: '{{ old('aumento') }}', aplicar: {{ old('aplicar') ? 'true' : 'false' }}, sueldo: {{ $sueldo }} }">
            <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-star-half-stroke text-gray-400 mr-1"></i> Nueva evaluación</h2>
            <p class="text-xs text-gray-500 mb-4">La nota va de 0 a 20. El aumento no se calcula con la nota: usted escribe cuánto sube, si corresponde.</p>
            <form method="POST" action="{{ route('rrhh.evaluaciones.store', $empleado) }}" class="grid gap-3">
                @csrf
                <div><label class="{{ $etq }}" for="e-periodo">Periodo evaluado *</label><input id="e-periodo" name="periodo" value="{{ old('periodo', now()->format('Y-m')) }}" required maxlength="40" class="{{ $caja }}" placeholder="2026-11 o Segundo trimestre">@error('periodo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $etq }}" for="e-nota">Nota (0 a 20) *</label><input id="e-nota" type="number" name="nota" step="0.25" min="0" max="20" value="{{ old('nota') }}" required class="{{ $caja }}">@error('nota')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $etq }}" for="e-com">Comentario</label><textarea id="e-com" name="comentario" rows="3" maxlength="2000" class="{{ $caja }}" placeholder="Fortalezas, puntos a mejorar…">{{ old('comentario') }}</textarea></div>
                <div class="border-t border-dashed border-gray-200 pt-3">
                    <label class="{{ $etq }}" for="e-aum">Aumento que decide dar (S/) <span class="font-normal text-gray-400">(opcional)</span></label>
                    <input id="e-aum" type="number" name="aumento" step="0.01" min="0" x-model="aumento" class="{{ $caja }}" placeholder="0.00">
                    <label class="mt-2 flex items-start gap-2 text-sm text-gray-700"><input type="hidden" name="aplicar" value="0"><input type="checkbox" name="aplicar" value="1" x-model="aplicar" class="mt-0.5 rounded border-gray-300"> <span>Aplicarlo ahora al sueldo básico</span></label>
                    <p class="text-xs text-gray-500 mt-1" x-show="aplicar && Number(aumento) > 0" x-cloak>Sueldo actual S/ {{ number_format($sueldo, 2) }} → <strong x-text="'S/ ' + (sueldo + Number(aumento)).toFixed(2)"></strong></p>
                </div>
                <button class="bg-gray-900 hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-lg">Guardar evaluación</button>
            </form>
        </section>

        <section class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
            <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Historial ({{ $evaluaciones->count() }})@if($evaluaciones->isNotEmpty()) <span class="text-sm font-normal text-gray-500">· promedio {{ number_format($evaluaciones->avg('nota'), 2) }}</span>@endif</h2>
            @forelse($evaluaciones as $ev)
                @php [$nivel, $clase] = \App\Models\EvaluacionEmpleado::nivel($ev->nota); @endphp
                <div class="px-5 py-4 border-b border-gray-100 last:border-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center justify-center min-w-[3.2rem] px-2 py-1 text-lg font-bold rounded-lg {{ $clase }}">{{ number_format($ev->nota, 2) }}</span>
                        <div class="min-w-0 flex-1"><p class="font-semibold text-gray-900">{{ $ev->periodo }} <span class="text-xs font-normal text-gray-500">· {{ $nivel }} · {{ $ev->created_at->format('d/m/Y') }}{{ $ev->evaluador ? ' · ' . $ev->evaluador->name : '' }}</span></p>
                            @if($ev->comentario)<p class="text-sm text-gray-600 mt-0.5">{{ $ev->comentario }}</p>@endif
                            @if($ev->aumento > 0)
                                <p class="text-sm mt-1 {{ $ev->sueldo_nuevo !== null ? 'text-emerald-700' : 'text-gray-600' }}"><i class="fas fa-arrow-trend-up mr-1"></i>Aumento de S/ {{ number_format($ev->aumento, 2) }}
                                    @if($ev->sueldo_nuevo !== null) aplicado: S/ {{ number_format($ev->sueldo_anterior, 2) }} → S/ {{ number_format($ev->sueldo_nuevo, 2) }}@else decidido, sin aplicar al sueldo @endif</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('rrhh.evaluaciones.destroy', [$empleado, $ev]) }}" data-confirm="¿Eliminar esta evaluación? Si el aumento ya se aplicó, el sueldo no vuelve atrás.">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Eliminar</button></form>
                    </div>
                </div>
            @empty
                <p class="p-6 text-sm text-gray-500">Todavía no hay evaluaciones de esta persona.</p>
            @endforelse
        </section>
    </div>
@endsection
