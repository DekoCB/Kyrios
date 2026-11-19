@extends('layouts.modulo')
@section('titulo', 'Cuentas de redes')
@section('subtitulo', 'Las cuentas oficiales de la empresa y su estado de conexión')

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Cuenta; @endphp
<div class="bg-blue-50 border border-blue-200 text-blue-900 text-sm rounded-lg px-4 py-3 mb-6">
    <i class="fas fa-circle-info mr-1"></i>Por ahora los datos de cada red se <strong>cargan a mano o se importan</strong> desde un archivo (por ejemplo, lo que se descarga de Metricool).
    El «estado» indica cómo está la cuenta en esa herramienta. La conexión directa con las redes se agregará más adelante.
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                <th class="text-left px-4 py-3">Cuenta</th><th class="text-left px-4 py-3">Estado</th><th class="text-left px-4 py-3">Responsable</th><th class="text-right px-4 py-3">Seguidores</th><th></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($cuentas as $c)
                <tr class="{{ $c->activa ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('marketing.cuentas.show', $c) }}" class="flex items-center gap-2 font-semibold text-gray-800 hover:text-blue-700">
                            <span class="inline-flex w-8 h-8 rounded-full items-center justify-center {{ Marketing::claseRed($c->red) }}"><i class="{{ Marketing::iconoRed($c->red) }}"></i></span>
                            <span>{{ $c->nombre }}<span class="block text-xs font-normal text-gray-400">{{ $c->red_nombre }}{{ $c->usuario ? ' · ' . $c->usuario : '' }}{{ $c->activa ? '' : ' · inactiva' }}</span></span>
                        </a>
                    </td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $c->estado === 'conectada' ? 'bg-green-100 text-green-800' : ($c->estado === 'desconectada' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">{{ $c->estado_nombre }}</span>
                        <span class="block text-xs text-gray-400 mt-0.5">vía {{ $c->origen }}</span></td>
                    <td class="px-4 py-3 text-gray-600">{{ $c->responsable?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ Marketing::numero($c->seguidores_actuales) }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('marketing.cuentas.show', $c) }}" class="text-blue-700 hover:underline">Abrir</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aún no hay cuentas. Agrega la primera con el formulario.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('marketing.cuentas.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 h-fit">
        @csrf
        <h2 class="font-bold text-gray-800">Agregar cuenta</h2>
        @include('marketing.cuentas._campos', ['cuenta' => new Cuenta(['estado' => 'conectada', 'origen' => 'Metricool'])])
        <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Agregar cuenta</button>
    </form>
</div>
@endsection
