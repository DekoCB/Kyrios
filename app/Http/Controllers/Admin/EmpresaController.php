<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Administración');
    }

    public function consultarRuc(string $ruc)
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return response()->json(['error' => 'RUC inválido'], 422);
        }

        $token = config('services.reniec.token') ?: 'apis-token-demo';

        $response = Http::withToken($token)
            ->timeout(8)
            ->get('https://api.apis.net.pe/v1/ruc', ['numero' => $ruc]);

        if ($response->failed()) {
            return response()->json(['error' => 'No se pudo consultar el RUC.'], 502);
        }

        $data = $response->json();
        $ubigeo = is_array($data['ubigeo'] ?? null)
            ? ($data['ubigeo'][0] ?? '')
            : ($data['ubigeo'] ?? '');

        return response()->json([
            'ruc'              => $data['numeroDocumento'] ?? $ruc,
            'razon_social'     => $data['nombre'] ?? '',
            'nombre_comercial' => '',
            'direccion'        => $data['direccion'] ?? '',
            'departamento'     => $data['departamento'] ?? '',
            'provincia'        => $data['provincia'] ?? '',
            'distrito'         => $data['distrito'] ?? '',
            'ubigeo'           => $ubigeo,
            'estado'           => $data['estado'] ?? '',
            'condicion'        => $data['condicion'] ?? '',
        ]);
    }

    public function edit()
    {
        $empresa = Empresa::first() ?? new Empresa();
        return view('admin.empresa.edit', compact('empresa'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'ruc'               => 'required|digits:11',
            'razon_social'      => 'required|string|max:200',
            'nombre_comercial'  => 'nullable|string|max:200',
            'direccion'         => 'nullable|string|max:300',
            'ubigeo'            => 'nullable|string|max:6',
            'departamento'      => 'nullable|string|max:100',
            'provincia'         => 'nullable|string|max:100',
            'distrito'          => 'nullable|string|max:100',
            'regimen'           => 'required|in:RER,RG,RMT,RUS',
            'telefono'          => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:150',
            'web'               => 'nullable|url|max:200',
            'facebook'          => 'nullable|string|max:200',
            'instagram'         => 'nullable|string|max:200',
            'tiktok'            => 'nullable|string|max:200',
            'sunat_usuario_sol' => 'nullable|string|max:100',
            'sunat_clave_sol'   => 'nullable|string|max:100',
            'sunat_modo'        => 'nullable|in:beta,produccion',
            'certificado_pfx'          => 'nullable|file|max:5120',
            'certificado_pfx_password' => 'nullable|string|max:200',
            'gre_client_id'     => 'nullable|string|max:200',
            'gre_client_secret' => 'nullable|string|max:200',
            'logo'              => 'nullable|image|max:2048',
            'logo_pdf'          => 'nullable|image|max:2048',
        ], [
            'ruc.digits'            => 'El RUC debe tener exactamente 11 dígitos',
            'razon_social.required' => 'La razón social es obligatoria',
        ]);

        $empresa = Empresa::first() ?? new Empresa();

        // Logos
        if ($request->hasFile('logo')) {
            if ($empresa->logo_path) Storage::disk('public')->delete($empresa->logo_path);
            $validated['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        if ($request->hasFile('logo_pdf')) {
            if ($empresa->logo_pdf_path) Storage::disk('public')->delete($empresa->logo_pdf_path);
            $validated['logo_pdf_path'] = $request->file('logo_pdf')->store('logos', 'public');
        }

        // Certificado PFX → convertir a PEM
        if ($request->hasFile('certificado_pfx')) {
            $password = $request->input('certificado_pfx_password', '');
            if (empty($password) && $empresa->certificado_pfx_password) {
                $password = $empresa->certificado_pfx_password;
            }
            if (empty($password)) {
                return back()->withInput()->with('error', 'Debe ingresar la contraseña del certificado PFX.');
            }

            try {
                if (!$empresa->exists) {
                    $empresa->fill($validated);
                    $empresa->save();
                }

                $pemPath = app(CertificadoService::class)->convertirPfxAPem(
                    $request->file('certificado_pfx'),
                    $password,
                    $empresa
                );

                $validated['certificado_pfx_path'] = $request->file('certificado_pfx')->store('certificados', 'local');
                $validated['certificado_pem_path']  = $pemPath;
                $validated['certificado_pfx_password'] = $password;
            } catch (\Exception $e) {
                return back()->withInput()->with('error', 'Error al procesar certificado: ' . $e->getMessage());
            }
        }

        // No sobrescribir campos sensibles vacíos
        if (empty($validated['sunat_clave_sol'])) unset($validated['sunat_clave_sol']);
        if (!$request->hasFile('certificado_pfx') && empty($validated['certificado_pfx_password'])) {
            unset($validated['certificado_pfx_password']);
        }
        if (empty($validated['gre_client_secret'])) unset($validated['gre_client_secret']);

        unset($validated['logo'], $validated['logo_pdf'], $validated['certificado_pfx']);

        if ($empresa->exists) {
            $empresa->update($validated);
        } else {
            Empresa::create($validated);
        }

        return redirect()->route('admin.empresa.edit')
            ->with('success', 'Datos de la empresa actualizados correctamente.');
    }
}
