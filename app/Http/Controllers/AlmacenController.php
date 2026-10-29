<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    /**
     * Constructor - Administración y Logística
     */
    public function __construct()
    {
        $this->middleware('role:Administración,Logística');
    }

    /**
     * Mostrar listado de almacenes
     */
    public function index(Request $request)
    {
        $estado = $request->get('estado');

        $base = fn() => Almacen::with(['encargado.role', 'sucursal', 'trabajadores.role'])
            ->when($estado, fn($q) => $q->where('estado', $estado));

        $tiendas   = $base()->where('tipo', 'tienda')->orderBy('nombre')->get();
        $depositos  = $base()->whereIn('tipo', ['principal', 'deposito', 'temporal'])->orderBy('nombre')->get();

        $stats = [
            'total'    => Almacen::count(),
            'activos'  => Almacen::activos()->count(),
            'principal'=> Almacen::principal()->count(),
            'tiendas'  => Almacen::tiendas()->count(),
            'depositos'=> Almacen::depositos()->count(),
        ];

        $canCreate = in_array(auth()->user()->role->nombre, ['Administración', 'Logística']);
        $canEdit   = in_array(auth()->user()->role->nombre, ['Administración', 'Logística']);
        $canDelete  = auth()->user()->role->nombre === 'Administración';

        // Datos de todos los almacenes para poblar el modal de editar vía JS
        $almacenesData = Almacen::with('sucursal')->get()->map(fn($a) => [
            'id'             => $a->id,
            'nombre'         => $a->nombre,
            'estado'         => $a->estado,
            'telefono'       => $a->telefono ?? '',
            'direccion'      => $a->direccion ?? '',
            'codigo'         => $a->codigo,
            'es_tienda'      => $a->tipo === 'tienda',
            'sucursal_nombre'=> $a->sucursal?->nombre ?? '',
        ])->keyBy('id');

        return view('inventario.almacenes.index', compact(
            'tiendas', 'depositos', 'stats',
            'canCreate', 'canEdit', 'canDelete',
            'almacenesData'
        ));
    }

    /**
     * Redirigir a crear sucursal — todo almacén debe pertenecer a una sucursal (SUNAT)
     */
    public function create()
    {
        return redirect()
            ->route('admin.sucursales.create')
            ->with('info', 'Para crear un nuevo almacén, primero crea el establecimiento (sucursal) en SUNAT. El almacén se genera automáticamente.');
    }

    /**
     * Redirigir al flujo correcto
     */
    public function store(Request $request)
    {
        return redirect()
            ->route('admin.sucursales.create')
            ->with('info', 'Los almacenes se crean automáticamente al registrar un establecimiento (sucursal).');
    }

    /**
     * Mostrar detalle de un almacén con su stock
     */
    public function show(Almacen $almacen)
    {
        $almacen->load(['encargado', 'sucursal', 'movimientos' => fn($q) => $q->with('producto', 'variante')->latest()->limit(20)]);

        $almacenId = $almacen->id;

        // Stock de productos tipo "cantidad" desde stock_almacen
        // Una fila por variante: se suma por producto y se conserva el desglose por variante
        $filasStock = StockAlmacen::where('almacen_id', $almacenId)
            ->where('cantidad', '>', 0)
            ->with('variante:id,producto_id,codigo_kyrios,sku,acabado_1,acabado_2,tamano,socket,estado_dano')
            ->get();
        $stockCantidad = $filasStock->groupBy('producto_id')->map(fn ($f) => (int) $f->sum('cantidad'));
        $variantesPorProducto = $filasStock->whereNotNull('variante_id')->groupBy('producto_id');

        $todosIds = $stockCantidad->keys();

        if ($todosIds->isEmpty()) {
            $stockDetalle = collect();
        } else {
            $productos = \App\Models\Producto::with(['categoria', 'marca'])
                ->whereIn('id', $todosIds)
                ->get()
                ->keyBy('id');

            $stockDetalle = $todosIds->map(function ($pid) use ($productos, $stockCantidad, $variantesPorProducto) {
                $producto = $productos->get($pid);
                if (!$producto) return null;

                $stock = (int) ($stockCantidad[$pid] ?? 0);

                return [
                    'producto' => $producto,
                    'stock'    => $stock,
                    'variantes' => ($variantesPorProducto[$pid] ?? collect())
                        ->sortByDesc('cantidad')
                        ->map(fn ($f) => [
                            'codigo'   => $f->variante?->codigo_kyrios ?? $f->variante?->sku,
                            'detalle'  => $f->variante?->nombre_completo,
                            'cantidad' => (int) $f->cantidad,
                            'dano'     => $f->variante?->estado_dano,
                        ])->values(),
                ];
            })->filter()->sortByDesc('stock')->values();
        }

        return view('inventario.almacenes.show', compact('almacen', 'stockDetalle'));
    }

    /**
     * Mostrar formulario para editar almacén
     */
    public function edit(Almacen $almacen)
    {
        $usuarios = User::with('role')->where('estado', 'activo')->orderBy('name')->get();

        return view('inventario.almacenes.edit', compact('almacen', 'usuarios'));
    }

    /**
     * Actualizar almacén
     */
    public function update(Request $request, Almacen $almacen)
    {
        $validated = $request->validate([
            'nombre'    => 'required|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:20',
            'estado'    => 'required|in:activo,inactivo',
            'encargado_id' => 'nullable|exists:users,id',
            'tipo'      => 'nullable|in:principal,deposito,temporal',
        ]);

        // Un almacén vinculado a una sucursal es de tipo tienda y su tipo no se edita
        if ($almacen->sucursal_id) {
            unset($validated['tipo']);
        }

        $almacen->update($validated);

        return redirect()
            ->route('inventario.almacenes.index')
            ->with('success', 'Almacén actualizado exitosamente');
    }

    /**
     * Eliminar almacén
     */
    public function destroy(Almacen $almacen)
    {
        try {
            $almacen->delete();
            
            return redirect()
                ->route('inventario.almacenes.index')
                ->with('success', 'Almacén eliminado exitosamente');
                
        } catch (\Exception $e) {
            return redirect()
                ->route('inventario.almacenes.index')
                ->with('error', 'No se puede eliminar el almacén porque tiene movimientos registrados');
        }
    }
}