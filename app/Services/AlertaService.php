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

    /**
     * Envía un aviso a cada usuario activo de los roles dados, salvo que ya tenga
     * uno sin leer con la misma clave. Devuelve cuántos avisos nuevos se crearon.
     */
    public function avisar(array $roles, AvisoSistema $aviso): int
    {
        $creados = 0;

        User::where('estado', 'activo')
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', $roles))
            ->get()
            ->each(function (User $u) use ($aviso, &$creados) {
                $repetido = $u->unreadNotifications()->where('data->clave', $aviso->clave)->exists();
                if (!$repetido) {
                    $u->notify($aviso);
                    $creados++;
                }
            });

        return $creados;
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
