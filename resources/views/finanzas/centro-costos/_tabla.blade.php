{{-- Tabla de utilidad por una dimensión. Variables: $filas, $titulo, $s, $pct --}}
<section class="bg-white rounded-xl shadow-sm overflow-hidden">
    <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">{{ $titulo }}</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Concepto</th><th class="px-3 py-2 text-right">Unid.</th><th class="px-3 py-2 text-right">Ingresos</th><th class="px-3 py-2 text-right">Costo</th><th class="px-3 py-2 text-right">Utilidad</th><th class="px-3 py-2 text-right">Margen</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($filas as $r)
                    <tr><td class="px-4 py-2 font-medium text-gray-900">{{ $r['etiqueta'] }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $r['unidades']) }}</td><td class="px-3 py-2 text-right">{{ $s($r['ingreso']) }}</td><td class="px-3 py-2 text-right text-gray-600">{{ $s($r['costo']) }}</td>
                        <td class="px-3 py-2 text-right font-semibold {{ $r['utilidad'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $s($r['utilidad']) }}</td><td class="px-3 py-2 text-right">{{ $pct($r['margen']) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
