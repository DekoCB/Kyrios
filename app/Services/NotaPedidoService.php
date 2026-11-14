<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\NotaPedido;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AvisoSistema;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Notas de pedido: de una cotización confirmada a la preparación en almacén. */
class NotaPedidoService
{
    public function __construct(private AlertaService $alertas)
    {
    }

    /** Genera la nota de pedido de una cotización aceptada y avisa a almacén. */
    public function crearDesdeCotizacion(Cotizacion $cotizacion, User $por, ?string $fechaEntrega = null, ?string $observaciones = null): NotaPedido
    {
        $nota = DB::transaction(function () use ($cotizacion, $por, $fechaEntrega, $observaciones) {
            $cot = Cotizacion::lockForUpdate()->with('detalles')->findOrFail($cotizacion->id);

            if ($cot->estado !== 'aceptada') {
                throw new DomainException('Solo una cotización aceptada (confirmada por el cliente) puede generar una nota de pedido.');
            }
            if (NotaPedido::where('cotizacion_id', $cot->id)->where('estado', '!=', 'anulada')->exists()) {
                throw new DomainException('Esta cotización ya tiene una nota de pedido activa.');
            }

            $n = (int) NotaPedido::lockForUpdate()->max('id') + 1;
            $nota = NotaPedido::create([
                'codigo'        => 'NP-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
                'cotizacion_id' => $cot->id,
                'cliente_id'    => $cot->cliente_id,
                'almacen_id'    => $cot->almacen_id,
                'user_id'       => $por->id,
                'fecha_entrega' => $fechaEntrega,
                'observaciones' => $observaciones,
            ]);

            foreach ($cot->detalles as $d) {
                $nota->detalles()->create([
                    'producto_id' => $d->producto_id, 'variante_id' => $d->variante_id,
                    'descripcion' => $d->descripcion ?: 'Producto', 'cantidad' => $d->cantidad,
                ]);
            }

            return $nota;
        });

        $this->alertas->avisar([Role::LOGISTICA, Role::ADMINISTRACION], new AvisoSistema(
            "nota-pedido:{$nota->id}",
            'Nueva nota de pedido',
            "{$nota->codigo}: preparar los productos de la cotización {$cotizacion->codigo}" . ($nota->fecha_entrega ? ' para el ' . $nota->fecha_entrega->format('d/m/Y') : '') . '.',
            route('notas-pedido.show', $nota, false),
            'alerta'
        ));

        return $nota;
    }

    /** Cuántas unidades de cada línea ya están separadas; con todas completas la nota queda lista. */
    public function registrarPreparado(NotaPedido $nota, array $preparado, User $por): NotaPedido
    {
        return DB::transaction(function () use ($nota, $preparado, $por) {
            $nota = NotaPedido::lockForUpdate()->with('detalles')->findOrFail($nota->id);
            if (!in_array($nota->estado, ['pendiente', 'en_preparacion'], true)) {
                throw new DomainException('Esta nota de pedido ya no admite cambios de preparación.');
            }

            foreach ($nota->detalles as $d) {
                if (array_key_exists($d->id, $preparado)) {
                    $d->update(['preparado' => max(0, min((int) $preparado[$d->id], $d->cantidad))]);
                }
            }

            $nota->refresh()->load('detalles');
            $completa = $nota->detalles->every(fn ($d) => $d->preparado >= $d->cantidad);
            $nota->update($completa
                ? ['estado' => 'lista', 'lista_en' => now(), 'atendido_por' => $por->id]
                : ['estado' => $nota->detalles->sum('preparado') > 0 ? 'en_preparacion' : 'pendiente', 'atendido_por' => $por->id]);

            if ($completa) {
                $this->avisarLista($nota);
            }

            return $nota;
        });
    }

    public function cambiarEstado(NotaPedido $nota, string $estado, User $por): NotaPedido
    {
        $permitidas = [
            'pendiente'      => ['en_preparacion', 'anulada'],
            'en_preparacion' => ['lista', 'anulada'],
            'lista'          => ['entregada', 'en_preparacion', 'anulada'],
        ];
        if (!in_array($estado, $permitidas[$nota->estado] ?? [], true)) {
            throw new DomainException("No se puede pasar una nota {$nota->estado_nombre} a «" . (NotaPedido::ESTADOS[$estado][0] ?? $estado) . '».');
        }

        $datos = ['estado' => $estado, 'atendido_por' => $por->id];
        if ($estado === 'lista') {
            $datos['lista_en'] = now();
            $nota->detalles()->update(['preparado' => DB::raw('cantidad')]);
        }
        $nota->update($datos);

        if ($estado === 'lista') {
            $this->avisarLista($nota);
        }

        return $nota->refresh();
    }

    private function avisarLista(NotaPedido $nota): void
    {
        $nota->creador?->notify(new AvisoSistema(
            "nota-pedido-lista:{$nota->id}", 'Pedido preparado', "{$nota->codigo} está lista para entregar.", route('notas-pedido.show', $nota, false), 'info'
        ));
    }
}
