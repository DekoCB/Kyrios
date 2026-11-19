<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Medio;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Biblioteca multimedia: imágenes, videos y diseños que se usan en las publicaciones. */
class MedioController extends Controller
{
    public function index(Request $request)
    {
        $medios = Medio::with(['producto', 'usuario'])->withCount([])
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('titulo', 'like', '%' . $request->q . '%')->orWhere('etiquetas', 'like', '%' . $request->q . '%')))
            ->orderByDesc('id')->paginate(24)->withQueryString();

        $uso = \DB::table('mkt_publicacion_medio')->whereIn('medio_id', $medios->pluck('id'))->selectRaw('medio_id, COUNT(*) as n')->groupBy('medio_id')->pluck('n', 'medio_id');

        return view('marketing.medios.index', [
            'medios'    => $medios,
            'uso'       => $uso,
            'productos' => Producto::where('estado', 'activo')->orderBy('nombre')->limit(500)->get(['id', 'nombre', 'nombre_fabrica', 'nombre_kyrios', 'codigo']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'archivos'   => 'required|array|min:1|max:20',
            'archivos.*' => 'file|max:51200|mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,pdf,ai,psd,zip',
            'titulo'     => 'nullable|string|max:150',
            'etiquetas'  => 'nullable|string|max:255',
            'producto_id' => 'nullable|exists:productos,id',
        ], [
            'archivos.required' => 'Elige al menos un archivo.',
            'archivos.*.mimes'  => 'Formatos permitidos: imágenes, videos MP4/MOV/WEBM, PDF, AI, PSD o ZIP.',
            'archivos.*.max'    => 'Cada archivo puede pesar hasta 50 MB.',
        ]);

        $subidos = 0;
        foreach ($request->file('archivos') as $archivo) {
            $original = $archivo->getClientOriginalName();
            $ruta = $archivo->store('marketing/medios', Medio::DISCO);
            Medio::create([
                'titulo'          => ($request->filled('titulo') && count($request->file('archivos')) === 1) ? $request->titulo : pathinfo($original, PATHINFO_FILENAME),
                'tipo'            => Medio::tipoPorMime($archivo->getMimeType()),
                'ruta'            => $ruta,
                'nombre_original' => $original,
                'mime'            => $archivo->getMimeType(),
                'tamano'          => $archivo->getSize(),
                'etiquetas'       => $request->etiquetas,
                'producto_id'     => $request->producto_id,
                'user_id'         => auth()->id(),
            ]);
            $subidos++;
        }

        return back()->with('success', $subidos === 1 ? 'Archivo subido a la biblioteca.' : "{$subidos} archivos subidos a la biblioteca.");
    }

    public function update(Request $request, Medio $medio)
    {
        $medio->update($request->validate([
            'titulo'      => 'required|string|max:150',
            'etiquetas'   => 'nullable|string|max:255',
            'producto_id' => 'nullable|exists:productos,id',
        ]));

        return back()->with('success', 'Datos del archivo guardados.');
    }

    public function destroy(Medio $medio)
    {
        Storage::disk(Medio::DISCO)->delete($medio->ruta);
        $medio->delete();

        return back()->with('success', 'Archivo eliminado de la biblioteca.');
    }
}
