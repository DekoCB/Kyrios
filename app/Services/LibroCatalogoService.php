<?php

namespace App\Services;

use App\Models\CatalogoLibro;
use App\Models\CatalogoLibroItem;
use App\Models\CatalogoLibroPagina;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\TipoCambio;
use App\Support\EtiquetaProducto;
use App\Support\FichaProducto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arma el libro de un catálogo: las hojas en el orden en que se hojean (índice, páginas de producto, notas y
 * contraportada), con los datos de cada producto ya listos para mostrar, y arma páginas automáticamente.
 * Lo usan el visor, el enlace público y el PDF, para que los tres muestren lo mismo.
 */
class LibroCatalogoService
{
    /** Tope de productos al armar automáticamente, para que el libro siga siendo manejable. */
    public const MAX_AUTO = 300;

    /**
     * @return array{paginas: array<int, array>, secciones: array<int, array{titulo: string, idx: int}>, contenido: int, diseno: array, tipo: string}
     *   paginas[0] es el índice (interior de la portada) y la última, la contraportada. El total siempre es impar,
     *   porque cada hoja lleva dos páginas y la portada va aparte.
     */
    public function construir(CatalogoLibro $c, bool $paraPdf = false): array
    {
        $diseno = $c->disenoFinal();
        $paginas = $c->paginas()->with(['items.variante.producto.tipoLuminaria', 'items.variante.producto.marca', 'items.variante.imagenes'])->get();
        $variantes = $paginas->flatMap(fn ($p) => $p->items->pluck('variante'))->filter()->unique('id');
        $precios = $this->precios($variantes);

        $q = [['t' => 'indice']];
        $secciones = [];
        foreach ($paginas as $i => $p) {
            $items = $p->items->map(fn ($it) => $it->variante ? $this->item($it->variante, $precios, $paraPdf, $it, $diseno) : null)->filter()->values()->all();
            if ($p->tipo === 'seccion') {
                $secciones[] = ['titulo' => (string) $p->titulo, 'idx' => $i + 1];
            }
            $q[] = ['t' => $p->tipo, 'titulo' => $p->titulo, 'items' => $items];
        }
        if (count($q) % 2 === 1) {
            $q[] = ['t' => 'notas'];
        }
        $q[] = ['t' => 'contra'];

        return ['paginas' => $q, 'secciones' => $secciones, 'contenido' => count($q) - 2, 'diseno' => $diseno, 'tipo' => $c->tipo ?: 'tecnico'];
    }

    /**
     * @param CatalogoLibroItem|null $it  lo elegido para este producto en la página: fotos, tamaño y características
     * @param array $diseno               el diseño del catálogo (`disenoFinal()`)
     */
    private function item(ProductoVariante $v, Collection $precios, bool $paraPdf, ?CatalogoLibroItem $it = null, array $diseno = []): array
    {
        $p = $v->producto;
        $datos = $p ? (FichaProducto::producto($p) + FichaProducto::variante($v)) : [];
        $specs = [];
        $campos = [
            'Acabado' => ['Acabado', 'Color'], 'Material' => ['Material principal'], 'Potencia' => ['Potencia'],
            'Temperatura de color' => ['Temperatura de color'], 'IP' => ['IP'], 'Alto' => ['Alto'], 'Diámetro' => ['Diámetro'], 'Largo' => ['Largo'],
        ];
        // Características que se muestran: las elegidas para este producto o, si no, las del diseño del catálogo
        $mostrar = $it?->caracteristicas ?? ($diseno['caracteristicas'] ?? CatalogoLibro::CARACTERISTICAS);
        foreach ($campos as $etiqueta => $claves) {
            if (!in_array($etiqueta, $mostrar, true)) {
                continue;
            }
            foreach ($claves as $k) {
                if (filled($datos[$k] ?? null)) {
                    $specs[$etiqueta] = $datos[$k];
                    break;
                }
            }
        }
        // Una potencia absurda casi siempre es un dato mal importado: mejor no mostrarla que mostrar un error
        if (isset($specs['Potencia']) && (float) $specs['Potencia'] > 500) {
            unset($specs['Potencia']);
        }
        // Fotos: las elegidas para este producto, en ese orden; si no eligieron, la principal
        $todas = $v->imagenes->sortBy(fn ($i) => [$i->es_principal ? 0 : 1, $i->orden])->values();
        $elegidas = collect($it?->imagenes ?? [])->map(fn ($id) => $todas->firstWhere('id', (int) $id))->filter()->values();
        $fotos = ($elegidas->isNotEmpty() ? $elegidas : $todas->take(1))->take(4)->map(fn ($f) => $this->foto($f, $paraPdf))->all();
        $img = $fotos[0] ?? null;
        $tam = isset(CatalogoLibroItem::TAMANOS[$it?->tamano ?? '']) ? $it->tamano : 'grande';
        $sinIgv = $this->precioSinIgv($v, $precios);

        return [
            'id' => $v->id,
            'cod' => $v->codigo_kyrios,
            'nombre' => $p ? EtiquetaProducto::nombre($p) : 'Producto',
            'sub' => trim(($p?->tipoLuminaria?->nombre ?? '') . ($p?->marca ? ' · ' . $p->marca->nombre : ''), ' ·'),
            'specs' => $specs,
            'img' => $img['url'] ?? null,
            'img_data' => $img['data'] ?? null,
            'img_ratio' => $img['ratio'] ?? null,
            'fotos' => $fotos,
            'tam' => $tam,
            'esc' => CatalogoLibroItem::TAMANOS[$tam],
            'pen' => $sinIgv,
            'usd' => $sinIgv !== null ? round($sinIgv / TipoCambio::actual(), 2) : null,
        ];
    }

    /** Una foto lista para mostrar: dirección en pantalla; en el PDF, la imagen incrustada y su proporción. */
    private function foto(\App\Models\ProductoImagen $img, bool $paraPdf): array
    {
        return $paraPdf
            ? ['url' => null, 'data' => FichaProducto::imagenDataUri($img), 'ratio' => $this->proporcion($img)]
            : ['url' => $img->url, 'data' => null, 'ratio' => null];
    }

    /** Ancho / alto de la foto; dompdf no ajusta solo una imagen a su caja, así que el PDF necesita la proporción. */
    private function proporcion(\App\Models\ProductoImagen $img): ?float
    {
        $ruta = \Illuminate\Support\Facades\Storage::disk('public')->path($img->ruta);
        $t = is_file($ruta) ? @getimagesize($ruta) : false;

        return $t && $t[1] > 0 ? $t[0] / $t[1] : null;
    }

    /** @return Collection<int, ProductoPrecio> */
    private function precios(Collection $variantes): Collection
    {
        if ($variantes->isEmpty()) {
            return collect();
        }

        return ProductoPrecio::where('tipo_precio', 'venta_regular')->where('activo', true)->whereNull('almacen_id')
            ->whereIn('producto_id', $variantes->pluck('producto_id')->unique())
            ->orderByDesc('prioridad')->get();
    }

    /** Precio de venta en soles sin IGV (los catálogos se muestran «sin IGV»). */
    private function precioSinIgv(ProductoVariante $v, Collection $precios): ?float
    {
        $p = $precios->first(fn ($x) => $x->producto_id === $v->producto_id && $x->variante_id === $v->id)
            ?? $precios->first(fn ($x) => $x->producto_id === $v->producto_id && $x->variante_id === null);
        if (!$p) {
            return null;
        }

        return round((float) $p->precio_soles / ($p->incluye_igv ? CotizacionService::IGV : 1), 2);
    }

    /**
     * Variantes que se pueden poner en un catálogo: activas y con foto. Filtra por tipo de luminaria, marca y texto.
     *
     * @param array{tipo_luminaria_id?: ?int, marca_id?: ?int, q?: ?string} $f
     */
    public function variantesDisponibles(array $f = [])
    {
        return ProductoVariante::query()->where('producto_variantes.estado', 'activo')->whereHas('imagenes')
            ->whereHas('producto', function ($q) use ($f) {
                $q->where('estado', 'activo');
                if (!empty($f['tipo_luminaria_id'])) {
                    $q->where('tipo_luminaria_id', $f['tipo_luminaria_id']);
                }
                if (!empty($f['marca_id'])) {
                    $q->where('marca_id', $f['marca_id']);
                }
                if (filled($f['q'] ?? null)) {
                    $t = '%' . $f['q'] . '%';
                    $q->where(fn ($w) => $w->where('nombre', 'like', $t)->orWhere('nombre_fabrica', 'like', $t)->orWhere('nombre_kyrios', 'like', $t));
                }
            })
            ->with(['producto.tipoLuminaria', 'producto.marca']);
    }

    /**
     * Reemplaza las páginas del catálogo por unas armadas solas: una portada de sección por tipo de luminaria y los
     * productos en páginas de cuatro (o dos, o uno al final). Devuelve cuántos productos entraron.
     *
     * @param array<int>|null $variantesIds si se indican, se arma solo con esas variantes en lugar de los filtros
     */
    public function armarAuto(CatalogoLibro $c, array $filtros = [], ?array $variantesIds = null): int
    {
        $q = $variantesIds !== null
            ? ProductoVariante::whereIn('id', $variantesIds)->with(['producto.tipoLuminaria'])
            : $this->variantesDisponibles($filtros);
        $variantes = $q->get()
            ->sortBy(fn ($v) => sprintf('%s|%s|%010d', $v->producto?->tipoLuminaria?->nombre ?? 'zzz', mb_strtolower($v->producto?->nombre ?? ''), $v->id))
            ->take(self::MAX_AUTO);
        $grupos = $variantes->groupBy(fn ($v) => $this->tituloSeccion($v->producto?->tipoLuminaria?->nombre));

        DB::transaction(function () use ($c, $grupos) {
            $c->paginas()->delete();
            $orden = 0;
            foreach ($grupos as $titulo => $vs) {
                $this->crearPagina($c, ++$orden, 'seccion', $titulo, [$vs->first()->id]);
                foreach ($this->partir($vs->pluck('id')->all(), $c->tipo ?: 'tecnico') as [$tipo, $ids]) {
                    $this->crearPagina($c, ++$orden, $tipo, null, $ids);
                }
            }
        });

        return $variantes->count();
    }

    /**
     * Parte una lista en páginas. Técnico: de 4; el resto (1 a 3) va en una de 2 + una de 1, o en una sola.
     * Brochure: alterna 4, 3, 4 y 2 productos para que las páginas no se vean iguales.
     */
    public function partir(array $ids, string $tipo = 'tecnico'): array
    {
        $paginas = [];
        $i = 0;
        $n = count($ids);
        if ($tipo === 'brochure') {
            $ciclo = [4, 3, 4, 2];
            $nombres = [1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro'];
            for ($k = 0; $i < $n; $k++) {
                $cuantos = min($ciclo[$k % 4], $n - $i);
                $paginas[] = [$nombres[$cuantos], array_slice($ids, $i, $cuantos)];
                $i += $cuantos;
            }

            return $paginas;
        }
        while ($i < $n) {
            $resto = $n - $i;
            $cuantos = $resto >= 4 ? 4 : ($resto === 3 ? 2 : $resto);
            $paginas[] = [$cuantos === 4 ? 'cuatro' : ($cuantos === 2 ? 'dos' : 'uno'), array_slice($ids, $i, $cuantos)];
            $i += $cuantos;
        }

        return $paginas;
    }

    public function crearPagina(CatalogoLibro $c, int $orden, string $tipo, ?string $titulo, array $varianteIds): CatalogoLibroPagina
    {
        $pagina = $c->paginas()->create(['orden' => $orden, 'tipo' => $tipo, 'titulo' => $titulo]);
        foreach (array_values($varianteIds) as $i => $id) {
            $pagina->items()->create(['variante_id' => $id, 'orden' => $i]);
        }

        return $pagina;
    }

    public function tituloSeccion(?string $tipoLuminaria): string
    {
        $t = trim((string) $tipoLuminaria);

        return $t === '' ? 'Otros' : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    /** Copia un catálogo con todas sus páginas, como borrador y con enlace propio (apagado). */
    public function duplicar(CatalogoLibro $c, ?int $usuarioId): CatalogoLibro
    {
        return DB::transaction(function () use ($c, $usuarioId) {
            $n = CatalogoLibro::create([
                'nombre' => mb_substr($c->nombre, 0, 105) . ' (copia)', 'tipo' => $c->tipo ?: 'tecnico', 'temporada' => $c->temporada, 'anio' => $c->anio, 'estado' => 'borrador',
                'vigencia' => $c->vigencia, 'precio_enlace' => $c->precio_enlace ?? 'none', 'diseno' => $c->diseno, 'creado_por' => $usuarioId,
            ]);
            foreach ($c->paginas()->with('items')->get() as $p) {
                $copia = $n->paginas()->create(['orden' => $p->orden, 'tipo' => $p->tipo, 'titulo' => $p->titulo]);
                foreach ($p->items as $it) {
                    $copia->items()->create(['variante_id' => $it->variante_id, 'orden' => $it->orden, 'imagenes' => $it->imagenes, 'tamano' => $it->tamano, 'caracteristicas' => $it->caracteristicas]);
                }
            }

            return $n;
        });
    }

    /** @return array{abierto: int, personas: int} */
    public function estadisticas(CatalogoLibro $c): array
    {
        return ['abierto' => $c->vistas()->count(), 'personas' => $c->vistas()->distinct('visitante')->count('visitante')];
    }
}
