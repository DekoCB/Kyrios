<?php

namespace App\Http\Controllers;

use App\Models\CajaChica;
use App\Models\CajaChicaAdjunto;
use App\Models\CajaChicaMovimiento;
use App\Models\User;
use App\Services\CajaChicaService;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Control de caja chica: la general y las asignadas a personal (técnicos, etc.), con fotos de los comprobantes.
 * El administrador ve y revisa todo; cada persona solo ve las cajas que se le asignaron.
 */
class CajaChicaController extends Controller
{
    public function __construct(private CajaChicaService $service)
    {
    }

    private function esAdmin(): bool
    {
        return auth()->user()->role?->nombre === 'Administración';
    }

    /** Admin: cualquier caja. Resto: solo la suya. */
    private function autorizar(CajaChica $caja): void
    {
        abort_unless($this->esAdmin() || $caja->responsable_id === auth()->id(), 403, 'No tiene acceso a esta caja chica.');
    }

    private function soloAdmin(): void
    {
        abort_unless($this->esAdmin(), 403, 'Solo el administrador puede hacer esto.');
    }

    private function reglasGasto(): array
    {
        return [
            'fecha'              => 'required|date|before_or_equal:today',
            'monto'              => 'required|numeric|min:0.01|max:99999',
            'categoria'          => ['required', Rule::in(array_keys(CajaChicaMovimiento::CATEGORIAS))],
            'concepto'           => 'required|string|max:255',
            'emisor'             => 'nullable|string|max:160',
            'tipo_comprobante'   => ['required', Rule::in(array_keys(CajaChicaMovimiento::COMPROBANTES))],
            'numero_comprobante' => 'nullable|string|max:40',
            'adjuntos'           => 'nullable|array|max:6',
            'adjuntos.*'         => 'file|mimes:jpg,jpeg,png,webp,heic,pdf|max:15360',
        ];
    }

    private function conErrores(callable $accion, string $ok)
    {
        try {
            $accion();
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }

    public function index(Request $request)
    {
        $cajas = CajaChica::with('responsable')
            ->when(!$this->esAdmin(), fn ($q) => $q->where('responsable_id', auth()->id()))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderByRaw("estado = 'activa' desc")->orderByDesc('id')
            ->get();

        $esAdmin = $this->esAdmin();

        return view('caja-chica.index', compact('cajas', 'esAdmin'));
    }

    public function create()
    {
        $this->soloAdmin();

        return view('caja-chica.create', ['usuarios' => $this->usuariosAsignables()]);
    }

    private function usuariosAsignables()
    {
        return User::with('role')->where('estado', 'activo')
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', \App\Models\Role::INTERNOS))
            ->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $this->soloAdmin();
        $datos = $request->validate([
            'tipo'           => 'required|in:general,asignada',
            'nombre'         => 'required|string|max:120',
            'responsable_id' => 'nullable|required_if:tipo,asignada|exists:users,id',
            'monto_inicial'  => 'nullable|numeric|min:0|max:99999',
            'observaciones'  => 'nullable|string|max:1000',
        ]);

        try {
            $caja = $this->service->abrir($datos, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('caja-chica.show', $caja)->with('success', "Caja chica {$caja->codigo} creada");
    }

    public function show(Request $request, CajaChica $caja)
    {
        $this->autorizar($caja);
        $caja->load('responsable');

        $movimientos = $caja->movimientos()->with(['registrador', 'revisor', 'adjuntos'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        $resumen = [
            'ingresos'   => $caja->total_ingresos,
            'gastos'     => $caja->total_gastos,
            'saldo'      => $caja->saldo,
            'por_revisar' => $caja->por_revisar,
        ];
        $esAdmin = $this->esAdmin();

        return view('caja-chica.show', compact('caja', 'movimientos', 'resumen', 'esAdmin'));
    }

    public function ingreso(Request $request, CajaChica $caja)
    {
        $this->soloAdmin();
        $d = $request->validate([
            'monto'    => 'required|numeric|min:0.01|max:99999',
            'concepto' => 'required|string|max:255',
            'fecha'    => 'required|date|before_or_equal:today',
        ]);

        return $this->conErrores(fn () => $this->service->ingreso($caja, (float) $d['monto'], $request->user(), $d['concepto'], $d['fecha']), 'Dinero registrado en la caja');
    }

    public function gasto(Request $request, CajaChica $caja)
    {
        $this->autorizar($caja);
        $d = $request->validate($this->reglasGasto());

        return $this->conErrores(
            fn () => $this->service->registrarGasto($caja, $d, $request->file('adjuntos', []), $request->user()),
            'Gasto registrado' . ($this->esAdmin() ? '' : ' y enviado a revisión')
        );
    }

    /** Un gasto solo lo toca quien lo registró (o el administrador). */
    private function autorizarGasto(CajaChicaMovimiento $mov): void
    {
        $this->autorizar($mov->caja);
        abort_unless($this->esAdmin() || $mov->user_id === auth()->id(), 403, 'Este gasto lo registró otra persona.');
    }

    public function editarGasto(CajaChicaMovimiento $mov)
    {
        $this->autorizarGasto($mov);
        if ($mov->tipo !== 'gasto' || !in_array($mov->estado, ['pendiente', 'observado'], true) || !$mov->caja->esta_activa) {
            return redirect()->route('caja-chica.show', $mov->caja)->with('error', 'Este gasto ya no se puede editar.');
        }
        $mov->load('adjuntos');

        return view('caja-chica.editar-gasto', ['mov' => $mov, 'caja' => $mov->caja]);
    }

    public function actualizarGasto(Request $request, CajaChicaMovimiento $mov)
    {
        $this->autorizarGasto($mov);
        $d = $request->validate($this->reglasGasto());

        try {
            $this->service->actualizarGasto($mov, $d, $request->file('adjuntos', []), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('caja-chica.show', $mov->caja)->with('success', 'Gasto actualizado');
    }

    public function eliminarMovimiento(CajaChicaMovimiento $mov)
    {
        $this->autorizarGasto($mov);
        // El personal solo borra lo que aún no revisaron; el administrador, cualquier movimiento.
        abort_unless($this->esAdmin() || ($mov->tipo === 'gasto' && in_array($mov->estado, ['pendiente', 'observado'], true)), 403, 'Este gasto ya fue revisado.');

        return $this->conErrores(fn () => $this->service->eliminarMovimiento($mov), 'Movimiento eliminado');
    }

    public function revisar(Request $request, CajaChicaMovimiento $mov)
    {
        $this->soloAdmin();
        $d = $request->validate(['decision' => 'required|in:aprobar,observar,rechazar', 'motivo' => 'nullable|string|max:255']);

        return $this->conErrores(fn () => $this->service->revisar($mov, $d['decision'], $d['motivo'] ?? null, $request->user()), 'Gasto revisado');
    }

    public function cerrar(CajaChica $caja)
    {
        $this->soloAdmin();

        return $this->conErrores(fn () => $this->service->cerrar($caja), 'Caja chica cerrada');
    }

    /** Sirve el archivo del comprobante solo a quien puede ver la caja. */
    public function adjunto(CajaChicaAdjunto $adjunto)
    {
        $this->autorizar($adjunto->movimiento->caja);
        abort_unless(Storage::disk(CajaChicaService::DISCO)->exists($adjunto->ruta), 404);

        return Storage::disk(CajaChicaService::DISCO)->response($adjunto->ruta, $adjunto->nombre_original, ['Content-Type' => $adjunto->mime], 'inline');
    }

    public function eliminarAdjunto(CajaChicaAdjunto $adjunto)
    {
        $mov = $adjunto->movimiento;
        $this->autorizarGasto($mov);
        abort_unless($this->esAdmin() || in_array($mov->estado, ['pendiente', 'observado'], true), 403);

        return $this->conErrores(fn () => $this->service->eliminarAdjunto($adjunto), 'Archivo eliminado');
    }

    /** Rendición de la caja: hoja imprimible para firmar. */
    public function rendicion(CajaChica $caja)
    {
        $this->autorizar($caja);
        $caja->load('responsable');
        $movimientos = $caja->movimientos()->with(['registrador', 'adjuntos'])->orderBy('fecha')->orderBy('id')->get();
        $empresa = Empresa::first();

        return Pdf::loadView('pdf.caja-chica', ['caja' => $caja, 'movimientos' => $movimientos, 'empresa' => $empresa])
            ->setPaper('a4')
            ->stream("rendicion-{$caja->codigo}.pdf");
    }

    public function exportar(CajaChica $caja): StreamedResponse
    {
        $this->autorizar($caja);
        $movimientos = $caja->movimientos()->with(['registrador', 'adjuntos'])->orderBy('fecha')->orderBy('id');

        return response()->streamDownload(function () use ($movimientos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Tipo', 'Concepto', 'Categoría', 'Emisor', 'Comprobante', 'Número', 'Monto (S/)', 'Estado', 'Registrado por', 'Archivos'], ';');
            $movimientos->chunk(500, function ($lote) use ($out) {
                foreach ($lote as $m) {
                    fputcsv($out, [
                        $m->fecha->format('d/m/Y'), $m->tipo, $m->concepto, $m->categoria_nombre, $m->emisor, $m->comprobante_nombre,
                        $m->numero_comprobante, number_format((float) $m->monto, 2, '.', ''), $m->estado, $m->registrador?->name, $m->adjuntos->count(),
                    ], ';');
                }
            });
            fclose($out);
        }, "caja-chica-{$caja->codigo}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
