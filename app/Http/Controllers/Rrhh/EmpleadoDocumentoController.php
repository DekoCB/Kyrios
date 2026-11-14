<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Documentos del legajo (contrato, DNI, CV…): archivos privados, solo Administración. */
class EmpleadoDocumentoController extends Controller
{
    public function store(Request $request, Empleado $empleado)
    {
        $datos = $request->validate([
            'tipo'    => ['required', Rule::in(array_keys(EmpleadoDocumento::TIPOS))],
            'nombre'  => 'nullable|string|max:160',
            'vence'   => 'nullable|date',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        $archivo = $request->file('archivo');
        $ruta = Storage::disk(EmpleadoDocumento::DISCO)->putFile("rrhh/{$empleado->id}", $archivo);

        $empleado->documentos()->create([
            'tipo' => $datos['tipo'], 'nombre' => ($datos['nombre'] ?? null) ?: mb_substr($archivo->getClientOriginalName(), 0, 160),
            'ruta' => $ruta, 'mime' => $archivo->getClientMimeType(), 'vence' => $datos['vence'] ?? null, 'subido_por' => $request->user()->id,
        ]);

        return back()->with('success', 'Documento guardado en el legajo.');
    }

    public function descargar(EmpleadoDocumento $documento)
    {
        abort_unless(Storage::disk(EmpleadoDocumento::DISCO)->exists($documento->ruta), 404);

        return Storage::disk(EmpleadoDocumento::DISCO)->response($documento->ruta, $documento->nombre, ['Content-Type' => $documento->mime ?: 'application/octet-stream'], 'inline');
    }

    public function destroy(EmpleadoDocumento $documento)
    {
        Storage::disk(EmpleadoDocumento::DISCO)->delete($documento->ruta);
        $documento->delete();

        return back()->with('success', 'Documento eliminado.');
    }
}
