<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los roles pasan a ser solo cinco: Administración, Contador, Logística, Operaciones y Cliente.
 *
 *  Administrador -> Administración   (mismo id; conserva a sus usuarios)
 *  Almacenero    -> Logística
 *  Técnico       -> Operaciones
 *  Vendedor, Tienda, Cajero -> sus usuarios pasan a Administración (hoy ese rol incluye punto de venta y caja)
 *  Proveedor     -> sus usuarios pasan a Cliente (sin acceso interno)
 *  Contador y Cliente se crean si faltan; los roles viejos se eliminan.
 *
 * Idempotente: se puede ejecutar más de una vez.
 */
return new class extends Migration
{
    private const DESCRIPCIONES = [
        'Administración' => 'Acceso total: administración, inventario, facturación, pagos, caja, ventas, usuarios y configuración.',
        'Contador'       => 'Parte administrativa: boletas, facturas, pagos, cuentas por cobrar y por pagar, y todos los reportes (solo lectura en el resto).',
        'Logística'      => 'Manejo de fábricas y proveedores: compras, pedidos, inventario, traslados, guías de remisión y catálogo.',
        'Operaciones'    => 'Programación de tareas internas y externas (instalaciones). Consulta de stock, traslados y guías, y su caja chica.',
        'Cliente'        => 'Portal del cliente: sus pedidos y el seguimiento de cada uno (próximamente).',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $id = fn (string $nombre) => DB::table('roles')->where('nombre', $nombre)->value('id');

            // Renombres que conservan el id (y por tanto a sus usuarios)
            foreach (['Administrador' => 'Administración', 'Almacenero' => 'Logística', 'Técnico' => 'Operaciones'] as $viejo => $nuevo) {
                $idViejo = $id($viejo);
                if (!$idViejo) {
                    continue;
                }
                $idNuevo = $id($nuevo);
                if ($idNuevo) {
                    // Ya existe el nuevo: los usuarios se pasan y el viejo se elimina más abajo
                    DB::table('users')->where('role_id', $idViejo)->update(['role_id' => $idNuevo]);
                } else {
                    DB::table('roles')->where('id', $idViejo)->update(['nombre' => $nuevo]);
                }
            }

            // Roles nuevos
            foreach (self::DESCRIPCIONES as $nombre => $descripcion) {
                if (!$id($nombre)) {
                    DB::table('roles')->insert(['nombre' => $nombre, 'descripcion' => $descripcion, 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    DB::table('roles')->where('nombre', $nombre)->update(['descripcion' => $descripcion]);
                }
            }

            // Roles que desaparecen: sus usuarios pasan al rol más cercano
            $administracion = $id('Administración');
            $cliente = $id('Cliente');
            foreach (['Vendedor' => $administracion, 'Tienda' => $administracion, 'Cajero' => $administracion, 'Proveedor' => $cliente] as $viejo => $destino) {
                $idViejo = $id($viejo);
                if (!$idViejo) {
                    continue;
                }
                DB::table('users')->where('role_id', $idViejo)->update(['role_id' => $destino]);
            }

            // Eliminar los roles viejos que queden sin usuarios
            DB::table('roles')->whereIn('nombre', ['Administrador', 'Almacenero', 'Técnico', 'Vendedor', 'Tienda', 'Cajero', 'Proveedor'])->delete();
        });
    }

    public function down(): void
    {
        // Vuelta razonable: los nombres antiguos equivalentes. Los usuarios que pasaron de Vendedor/Tienda/Cajero/Proveedor
        // a otro rol no se pueden recuperar (se desconoce de dónde venían).
        foreach (['Administración' => 'Administrador', 'Logística' => 'Almacenero', 'Operaciones' => 'Técnico'] as $nuevo => $viejo) {
            DB::table('roles')->where('nombre', $nuevo)->update(['nombre' => $viejo]);
        }
        foreach (['Vendedor', 'Tienda', 'Proveedor', 'Cajero'] as $viejo) {
            if (!DB::table('roles')->where('nombre', $viejo)->exists()) {
                DB::table('roles')->insert(['nombre' => $viejo, 'descripcion' => $viejo, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $cliente = DB::table('roles')->where('nombre', 'Cliente')->value('id');
        $contador = DB::table('roles')->where('nombre', 'Contador')->value('id');
        $admin = DB::table('roles')->where('nombre', 'Administrador')->value('id');
        if ($admin) {
            DB::table('users')->whereIn('role_id', array_filter([$cliente, $contador]))->update(['role_id' => $admin]);
        }
        DB::table('roles')->whereIn('nombre', ['Cliente', 'Contador'])->delete();
    }
};
