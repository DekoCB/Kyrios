<?php

namespace App\Services;

use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AvisoSistema;
use App\Support\EtiquetaProducto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class AlertaService
{
    /** Roles que reciben los avisos de inventario. */
    public const ROLES_INVENTARIO = [Role::ADMINISTRACION, Role::LOGISTICA];

    /** Con cuántos días de anticipación empiezan los avisos de una fecha. */
    public const DIAS_ANTES = 3;

    /**
     * Envía un aviso a cada usuario activo de los roles dados, salvo que ya tenga
     * uno sin leer con la misma clave. Devuelve cuántos avisos nuevos se crearon.
     */
    public function avisar(array $roles, AvisoSistema $aviso, array|\Illuminate\Support\Collection $tambien = [], bool $unaVez = false): int
    {
        $destinatarios = User::where('estado', 'activo')
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', $roles))
            ->get()
            ->merge(collect($tambien)->filter(fn ($u) => $u && $u->estado === 'activo'))
            ->unique('id');

        $creados = 0;
        foreach ($destinatarios as $u) {
            // Con $unaVez, si ya recibió ese aviso (leído o no) no se repite; si no, solo mientras siga sin leer
            $consulta = $unaVez ? $u->notifications() : $u->unreadNotifications();
            if (!$consulta->where('data->clave', $aviso->clave)->exists()) {
                $u->notify($aviso);
                $creados++;
            }
        }

        return $creados;
    }

    /**
     * Borra los avisos sin leer de las claves con esos prefijos que ya no están vigentes
     * (por ejemplo, la etapa ya se cumplió o la fecha cambió). Devuelve cuántos retiró.
     */
    public function retirarObsoletos(array $prefijos, array $vigentes): int
    {
        $ids = DB::table('notifications')->whereNull('read_at')->get(['id', 'data'])
            ->filter(function ($n) use ($prefijos, $vigentes) {
                $clave = json_decode($n->data, true)['clave'] ?? '';
                foreach ($prefijos as $p) {
                    if (str_starts_with($clave, $p)) {
                        return !in_array($clave, $vigentes, true);
                    }
                }

                return false;
            })->pluck('id');

        DB::table('notifications')->whereIn('id', $ids)->delete();

        return $ids->count();
    }

    /**
     * Avisos por fechas: etapas de las órdenes de compra, instalaciones y entregas, y pedidos internos.
     * El día de aviso va en la clave, así que cada día se renueva y no se repite dentro del mismo día.
     *
     * @return array{avisos:int, retirados:int}
     */
    public function revisarFechas(): array
    {
        $vigentes = [];
        $avisos = 0;
        $internos = [Role::ADMINISTRACION, Role::LOGISTICA];
        $cuando = fn (int $d) => $d < 0 ? 'venció hace ' . abs($d) . ($d === -1 ? ' día' : ' días') : ($d === 0 ? 'es hoy' : ($d === 1 ? 'es mañana' : "faltan {$d} días"));

        // Etapas de seguimiento de órdenes abiertas
        $etapas = \App\Models\PedidoEtapa::with('pedido')->whereNull('fecha_real')->whereNotNull('fecha_prevista')
            ->whereHas('pedido', fn ($q) => $q->whereIn('estado', ['pendiente', 'aprobado']))->get();
        foreach ($etapas as $e) {
            $d = $e->dias_restantes;
            if ($d > self::DIAS_ANTES) {
                continue;
            }
            $clave = "seg:{$e->id}:" . ($d < 0 ? "a{$d}" : "d{$d}");
            $vigentes[] = $clave;
            $avisos += $this->avisar($internos, new AvisoSistema(
                $clave,
                $d < 0 ? 'Etapa atrasada' : 'Etapa próxima',
                "{$e->pedido->codigo} · «{$e->nombre}»: " . $cuando($d) . ' (' . $e->fecha_prevista->format('d/m/Y') . ').',
                route('pedidos.show', $e->pedido_id, false),
                $d < 0 ? 'alerta' : 'info'
            ), unaVez: true);
        }

        // Instalaciones y entregas programadas
        $eventos = \App\Models\CronogramaEvento::with('responsable')->programados()->get();
        foreach ($eventos as $ev) {
            $d = $ev->dias_restantes;
            if ($d > self::DIAS_ANTES) {
                continue;
            }
            $clave = "cron:{$ev->id}:" . ($d < 0 ? "a{$d}" : "d{$d}");
            $vigentes[] = $clave;
            $avisos += $this->avisar($internos, new AvisoSistema(
                $clave,
                ($ev->tipo === 'instalacion' ? 'Instalación' : 'Entrega') . ($d < 0 ? ' atrasada' : ' próxima'),
                "{$ev->titulo}: " . $cuando($d) . ' (' . $ev->fecha->format('d/m/Y') . ($ev->hora_corta ? " {$ev->hora_corta}" : '') . ').',
                route('cronograma.index', ['mes' => $ev->fecha->format('Y-m')], false),
                $d < 0 ? 'alerta' : 'info'
            ), [$ev->responsable], unaVez: true);
        }

        // Pedidos internos que esperan y ya casi vencen (o vencieron)
        $pedidos = \App\Models\PedidoInterno::with('solicitante')->pendientes()->whereNotNull('fecha_requerida')->get();
        foreach ($pedidos as $p) {
            $d = (int) today()->diffInDays($p->fecha_requerida, false);
            if ($d > 1) {
                continue;
            }
            $clave = "pi:{$p->id}:" . ($d < 0 ? "a{$d}" : "d{$d}");
            $vigentes[] = $clave;
            $avisos += $this->avisar($internos, new AvisoSistema(
                $clave,
                'Pedido interno por atender',
                "{$p->codigo} (" . ($p->area ?: $p->destino?->nombre) . '): ' . $cuando($d) . ' la fecha requerida.',
                route('pedidos-internos.show', $p->id, false),
                'alerta'
            ), [$p->solicitante], unaVez: true);
        }

        return ['avisos' => $avisos, 'retirados' => $this->retirarObsoletos(['seg:', 'cron:', 'pi:'], $vigentes)];
    }

    /** Variantes activas cuyo stock total llegó al mínimo indicado (mínimo mayor que cero). */
    public function variantesBajoMinimo()
    {
        $total = '(select coalesce(sum(s.cantidad), 0) from stock_almacen s where s.variante_id = producto_variantes.id)';

        return ProductoVariante::query()
            ->activas()
            ->where('stock_minimo', '>', 0)
            ->whereRaw("{$total} <= producto_variantes.stock_minimo")
            ->select('producto_variantes.*', DB::raw("{$total} as stock_total"))
            ->with('producto')
            ->orderBy('producto_variantes.id')
            ->get();
    }

    /**
     * Avisa de las variantes bajo el mínimo y retira los avisos sin leer de las que ya se repusieron.
     *
     * @return array{avisos:int, bajo_minimo:int, retirados:int}
     */
    public function revisarStockMinimo(): array
    {
        $bajas = $this->variantesBajoMinimo();
        $vigentes = [];
        $avisos = 0;

        foreach ($bajas as $v) {
            $clave = "stock-minimo:{$v->id}";
            $vigentes[] = $clave;
            $nombre = ($v->producto ? EtiquetaProducto::nombre($v->producto) : 'Producto') . ' · ' . ($v->codigo_kyrios ?: "variante {$v->id}");
            $url = $v->producto && Route::has('inventario.productos.show') ? route('inventario.productos.show', $v->producto, false) : null;

            $avisos += $this->avisar(self::ROLES_INVENTARIO, new AvisoSistema(
                $clave,
                'Stock mínimo alcanzado',
                "{$nombre}: quedan {$v->stock_total} (mínimo {$v->stock_minimo}).",
                $url,
                'alerta'
            ));
        }

        // Si el stock se repuso, el aviso pendiente ya no tiene sentido
        $retirados = DB::table('notifications')
            ->whereNull('read_at')
            ->where('data', 'like', '%"clave":"stock-minimo:%')
            ->get(['id', 'data'])
            ->filter(fn ($n) => !in_array(json_decode($n->data, true)['clave'] ?? null, $vigentes, true))
            ->pluck('id');
        DB::table('notifications')->whereIn('id', $retirados)->delete();

        return ['avisos' => $avisos, 'bajo_minimo' => $bajas->count(), 'retirados' => $retirados->count()];
    }
}
