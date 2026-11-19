{{-- Crecimiento de seguidores: un gráfico por cuenta (cada una con su propia escala). Variable: $serie (etiquetas y series por cuenta) --}}
@if(count($serie['series']) && count($serie['etiquetas']) >= 1)
<div class="mb-6">
    <h2 class="font-bold text-gray-800 mb-3">Crecimiento de seguidores</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($serie['series'] as $i => $s)
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm font-semibold text-gray-700 mb-2"><i class="{{ \App\Support\Marketing::iconoRed($s['red']) }} mr-1" style="color: {{ \App\Support\Marketing::colorRed($s['red']) }}"></i>{{ $s['nombre'] }}</p>
                <div style="height: 200px"><canvas id="grafico-seguidores-{{ $i }}" aria-label="Seguidores de {{ $s['nombre'] }}"></canvas></div>
            </div>
        @endforeach
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const serie = @json($serie);
        const colores = @json(array_map(fn ($s) => \App\Support\Marketing::colorRed($s['red']), $serie['series']));
        serie.series.forEach((s, i) => {
            new Chart(document.getElementById('grafico-seguidores-' + i), {
                type: 'line',
                data: { labels: serie.etiquetas, datasets: [{ label: s.nombre, data: s.datos, borderColor: colores[i], backgroundColor: colores[i], tension: 0.25, spanGaps: true, pointRadius: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, ticks: { precision: 0 } } } },
            });
        });
    })();
</script>
@else
<div class="bg-white rounded-xl shadow-sm p-5 mb-6 text-sm text-gray-500">Todavía no hay datos de seguidores en este período para dibujar el gráfico.</div>
@endif
