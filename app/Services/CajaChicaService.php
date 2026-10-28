<?php

namespace App\Services;

use App\Models\CajaChica;
use App\Models\CajaChicaAdjunto;
use App\Models\CajaChicaMovimiento;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cajas chicas: apertura, entregas de dinero, gastos con foto o escaneo del comprobante y su revisión.
 * Los archivos se guardan en el disco privado y solo se sirven a quien puede ver la caja.
 */
class CajaChicaService
{
    public const DISCO = 'local';
    private const LADO_MAX = 1800;

    public function abrir(array $datos, User $creador): CajaChica
    {
        return DB::transaction(function () use ($datos, $creador) {
            $tipo = $datos['tipo'] ?? 'asignada';

            if ($tipo === 'general' && CajaChica::where('tipo', 'general')->where('estado', 'activa')->lockForUpdate()->exists()) {
                throw new DomainException('Ya existe una caja chica general activa.');
            }
            if ($tipo === 'asignada' && empty($datos['responsable_id'])) {
                throw new DomainException('Elija a quién se le asigna la caja chica.');
            }

            $caja = CajaChica::create([
                'codigo'         => $this->siguienteCodigo(),
                'nombre'         => $datos['nombre'],
                'tipo'           => $tipo,
                'responsable_id' => $tipo === 'asignada' ? $datos['responsable_id'] : null,
                'observaciones'  => $datos['observaciones'] ?? null,
                'estado'         => 'activa',
                'creado_por'     => $creador->id,
            ]);

            if (($datos['monto_inicial'] ?? 0) > 0) {
                $this->ingreso($caja, (float) $datos['monto_inicial'], $creador, 'Entrega inicial', $datos['fecha'] ?? null);
            }

            return $caja;
        });
    }

    private function siguienteCodigo(): string
    {
        $n = (int) CajaChica::lockForUpdate()->max('id') + 1;

        return 'CC-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** Dinero que entra a la caja: entrega inicial o reposición. */
    public function ingreso(CajaChica $caja, float $monto, User $user, string $concepto = 'Reposición', $fecha = null): CajaChicaMovimiento
    {
        $this->exigirActiva($caja);
        if ($monto <= 0) {
            throw new DomainException('El monto debe ser mayor a cero.');
        }

        return $caja->movimientos()->create([
            'tipo' => 'ingreso', 'fecha' => $fecha ?: today(), 'monto' => $monto, 'concepto' => $concepto,
            'estado' => 'aprobado', 'user_id' => $user->id,
        ]);
    }

    /**
     * @param UploadedFile[] $archivos fotos o PDF del comprobante
     */
    public function registrarGasto(CajaChica $caja, array $datos, array $archivos, User $user): CajaChicaMovimiento
    {
        return DB::transaction(function () use ($caja, $datos, $archivos, $user) {
            $caja = CajaChica::lockForUpdate()->findOrFail($caja->id);
            $this->exigirActiva($caja);
            $this->validarGasto($datos, count($archivos), null);

            if ((float) $datos['monto'] > $caja->saldo + 0.001) {
                throw new DomainException('Saldo insuficiente en la caja: disponible S/ ' . number_format($caja->saldo, 2) . '.');
            }

            $mov = $caja->movimientos()->create($this->camposGasto($datos) + [
                'tipo' => 'gasto', 'user_id' => $user->id,
                'estado' => $user->role?->nombre === 'Administrador' ? 'aprobado' : 'pendiente',
            ]);

            $this->guardarAdjuntos($mov, $archivos);

            return $mov;
        });
    }

    /** Edita un gasto aún no aprobado; vuelve a quedar pendiente de revisión. */
    public function actualizarGasto(CajaChicaMovimiento $mov, array $datos, array $archivos, User $user): CajaChicaMovimiento
    {
        return DB::transaction(function () use ($mov, $datos, $archivos, $user) {
            $caja = CajaChica::lockForUpdate()->findOrFail($mov->caja_chica_id);
            $this->exigirActiva($caja);

            if ($mov->tipo !== 'gasto' || !in_array($mov->estado, ['pendiente', 'observado'], true)) {
                throw new DomainException('Solo se puede editar un gasto pendiente u observado.');
            }

            $this->validarGasto($datos, $mov->adjuntos()->count() + count($archivos), $mov);

            $disponible = $caja->saldo + (float) $mov->monto;
            if ((float) $datos['monto'] > $disponible + 0.001) {
                throw new DomainException('Saldo insuficiente en la caja: disponible S/ ' . number_format($disponible, 2) . '.');
            }

            $mov->update($this->camposGasto($datos) + [
                'estado' => $user->role?->nombre === 'Administrador' ? $mov->estado : 'pendiente',
                'motivo_revision' => null,
            ]);
            $this->guardarAdjuntos($mov, $archivos);

            return $mov->fresh();
        });
    }

    private function camposGasto(array $d): array
    {
        $sin = ($d['tipo_comprobante'] ?? null) === 'sin_comprobante';

        return [
            'fecha' => $d['fecha'], 'monto' => $d['monto'], 'categoria' => $d['categoria'] ?? 'otros',
            'concepto' => $d['concepto'], 'emisor' => $d['emisor'] ?? null,
            'tipo_comprobante' => $d['tipo_comprobante'] ?? 'sin_comprobante',
            'numero_comprobante' => $sin ? null : ($d['numero_comprobante'] ?? null),
        ];
    }

    private function validarGasto(array $d, int $nArchivos, ?CajaChicaMovimiento $actual): void
    {
        if ((float) ($d['monto'] ?? 0) <= 0) {
            throw new DomainException('El monto debe ser mayor a cero.');
        }

        $tipo = $d['tipo_comprobante'] ?? 'sin_comprobante';
        if ($tipo !== 'sin_comprobante' && $nArchivos === 0) {
            throw new DomainException('Suba la foto o el escaneo del comprobante (o marque «Sin comprobante»).');
        }

        // Mismo documento del mismo emisor ya registrado: suele ser un comprobante cargado dos veces.
        if ($tipo !== 'sin_comprobante' && !empty($d['numero_comprobante'])) {
            $repetido = CajaChicaMovimiento::where('tipo', 'gasto')->where('estado', '!=', 'rechazado')
                ->where('tipo_comprobante', $tipo)
                ->where('numero_comprobante', $d['numero_comprobante'])
                ->where('emisor', $d['emisor'] ?? null)
                ->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))
                ->first();
            if ($repetido) {
                throw new DomainException('Este comprobante ya fue registrado en la caja ' . $repetido->caja->codigo . ' (' . $repetido->fecha->format('d/m/Y') . ').');
            }
        }
    }

    /** Aprueba, observa o rechaza un gasto. Observar/rechazar exige un motivo. */
    public function revisar(CajaChicaMovimiento $mov, string $decision, ?string $motivo, User $revisor): CajaChicaMovimiento
    {
        if ($mov->tipo !== 'gasto') {
            throw new DomainException('Solo se revisan gastos.');
        }
        $estado = ['aprobar' => 'aprobado', 'observar' => 'observado', 'rechazar' => 'rechazado'][$decision] ?? null;
        if (!$estado) {
            throw new DomainException('Decisión no válida.');
        }
        if ($estado !== 'aprobado' && blank($motivo)) {
            throw new DomainException('Indique el motivo.');
        }

        return DB::transaction(function () use ($mov, $estado, $motivo, $revisor) {
            $caja = CajaChica::lockForUpdate()->findOrFail($mov->caja_chica_id);
            $mov->refresh();

            // Un gasto rechazado no cuenta en el saldo: volver a darlo por válido solo si todavía alcanza el dinero.
            if ($mov->estado === 'rechazado' && $estado !== 'rechazado' && (float) $mov->monto > $caja->saldo + 0.001) {
                throw new DomainException('No se puede reactivar este gasto: el saldo de la caja (S/ ' . number_format($caja->saldo, 2) . ') ya no lo cubre.');
            }

            $mov->update([
                'estado' => $estado, 'motivo_revision' => $estado === 'aprobado' ? null : $motivo,
                'revisado_por' => $revisor->id, 'revisado_en' => now(),
            ]);

            return $mov;
        });
    }

    public function eliminarMovimiento(CajaChicaMovimiento $mov): void
    {
        DB::transaction(function () use ($mov) {
            $caja = CajaChica::lockForUpdate()->findOrFail($mov->caja_chica_id);
            $this->exigirActiva($caja);

            // Quitar dinero que ya se gastó dejaría el saldo en negativo
            if ($mov->tipo === 'ingreso' && $caja->saldo - (float) $mov->monto < -0.001) {
                throw new DomainException('No se puede eliminar este ingreso: los gastos registrados superarían el saldo que quedaría (S/ ' . number_format($caja->saldo - (float) $mov->monto, 2) . ').');
            }

            $rutas = $mov->adjuntos()->pluck('ruta');
            $mov->delete();
            Storage::disk(self::DISCO)->delete($rutas->all());
        });
    }

    public function eliminarAdjunto(CajaChicaAdjunto $adjunto): void
    {
        $mov = $adjunto->movimiento;
        if ($mov->tipo === 'gasto' && $mov->tipo_comprobante !== 'sin_comprobante' && $mov->adjuntos()->count() <= 1) {
            throw new DomainException('El gasto necesita al menos un archivo del comprobante.');
        }
        Storage::disk(self::DISCO)->delete($adjunto->ruta);
        $adjunto->delete();
    }

    public function cerrar(CajaChica $caja): CajaChica
    {
        $this->exigirActiva($caja);
        if ($caja->movimientos()->where('tipo', 'gasto')->whereIn('estado', ['pendiente', 'observado'])->exists()) {
            throw new DomainException('Hay gastos pendientes de revisión. Apruébelos o recházelos antes de cerrar la caja.');
        }

        $caja->update(['estado' => 'cerrada', 'cerrada_en' => now()]);

        return $caja;
    }

    private function exigirActiva(CajaChica $caja): void
    {
        if (!$caja->esta_activa) {
            throw new DomainException('La caja chica está cerrada.');
        }
    }

    /** Guarda cada archivo; las fotos se reducen y comprimen para que el celular no llene el disco. */
    private function guardarAdjuntos(CajaChicaMovimiento $mov, array $archivos): void
    {
        foreach ($archivos as $archivo) {
            if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
                continue;
            }

            $dir = "caja_chica/{$mov->caja_chica_id}/{$mov->id}";
            $nombreOriginal = $archivo->getClientOriginalName();
            $mime = $archivo->getMimeType() ?: 'application/octet-stream';

            if (str_starts_with($mime, 'image/') && ($jpg = $this->reducirImagen($archivo->getRealPath()))) {
                $ruta = $dir . '/' . uniqid('', true) . '.jpg';
                Storage::disk(self::DISCO)->put($ruta, $jpg);
                $mime = 'image/jpeg';
                $tamano = strlen($jpg);
            } else {
                $ruta = Storage::disk(self::DISCO)->putFile($dir, $archivo);
                $tamano = $archivo->getSize();
            }

            $mov->adjuntos()->create([
                'ruta' => $ruta, 'nombre_original' => mb_substr($nombreOriginal, 0, 200), 'mime' => $mime, 'tamano' => $tamano,
            ]);
        }
    }

    private function reducirImagen(string $ruta): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring((string) file_get_contents($ruta));
        if (!$img) {
            return null;
        }

        // Respeta la orientación de la cámara del celular.
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($ruta);
            $giro = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
            if ($giro && ($rotada = imagerotate($img, $giro, 0))) {
                $img = $rotada;
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $factor = min(1, self::LADO_MAX / max($w, $h));
        if ($factor < 1) {
            $img = imagescale($img, (int) round($w * $factor), (int) round($h * $factor));
        }

        ob_start();
        imagejpeg($img, null, 82);

        return ob_get_clean() ?: null;
    }
}
