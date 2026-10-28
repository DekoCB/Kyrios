<?php

namespace Tests\Feature;

use App\Models\Catalogo\Color;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\Modelo;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Cliente;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Datos basura en cada formulario (crear, editar, borrar) del área de catálogo, clientes, usuarios, admin y perfil:
 * siempre debe responder con un error de validación o redirección, nunca con un 500.
 */
class Auditoria2CFuzzEscrituraTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIJOS = ['users', 'clientes', 'catalogo/', 'admin/empresa', 'admin/sucursales', 'profile', 'login', 'register', 'master-password', 'forgot-password'];

    private const CAMPOS = [
        'nombre', 'name', 'email', 'password', 'password_confirmation', 'current_password', 'telefono', 'direccion', 'tipo_documento', 'numero_documento',
        'ruc', 'razon_social', 'nombre_comercial', 'estado', 'role_id', 'almacen_id', 'codigo', 'descripcion', 'notas', 'contacto', 'segmento', 'comision',
        'fecha_cumpleanos', 'ubigeo', 'regimen', 'sunat_modo', 'abreviatura', 'tipo', 'categoria', 'hex', 'color_hex', 'marca_id', 'motivo', 'dni', 'tipo_sucursal',
        'es_principal', 'departamento', 'provincia', 'distrito', 'email_empresa', 'cargo', 'empresa', 'asesor', 'origen', 'redes_sociales', 'preferencias',
        'telefono_fijo', 'anexo', 'especialidad', 'token', 'redirect', 'to',
    ];

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
    }

    private function valorDe(string $param, User $admin)
    {
        $mapa = [
            'user' => fn () => User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Vendedor'])->id, 'estado' => 'activo'])->id,
            'cliente' => fn () => Cliente::create(['nombre' => 'Cliente Fuzz ' . uniqid(), 'estado' => 'activo'])->id,
            'marca' => fn () => Marca::create(['nombre' => 'Marca Fuzz ' . uniqid(), 'estado' => 'activo'])->id,
            'color' => fn () => Color::create(['nombre' => 'Color Fuzz ' . uniqid(), 'estado' => 'activo'])->id,
            'modelo' => fn () => Modelo::create(['nombre' => 'Modelo Fuzz ' . uniqid(), 'marca_id' => Marca::first()?->id ?? Marca::create(['nombre' => 'M' . uniqid(), 'estado' => 'activo'])->id, 'estado' => 'activo'])->id,
            'unidade' => fn () => UnidadMedida::first()?->id ?? 1,
            'unidad' => fn () => UnidadMedida::first()?->id ?? 1,
            'motivo' => fn () => MotivoMovimiento::first()?->id ?? 1,
            'sucursal' => fn () => Sucursal::first()?->id ?? 1,
        ];

        return isset($mapa[$param]) ? $mapa[$param]() : 1;
    }

    public static function cargas(): array
    {
        return [
            'vacia' => [[]],
            'arreglos' => [array_fill_keys(self::CAMPOS, ['x', 'y'])],
            'largos' => [array_fill_keys(self::CAMPOS, str_repeat('á', 6000))],
            'nulos' => [array_fill_keys(self::CAMPOS, "a\0b")],
            'numeros' => [array_fill_keys(self::CAMPOS, '-99999999999999999999')],
            'html' => [array_fill_keys(self::CAMPOS, '<img src=x onerror=alert(1)>')],
            'sql' => [array_fill_keys(self::CAMPOS, "1' OR '1'='1")],
            'vacios' => [array_fill_keys(self::CAMPOS, '')],
            'booleanos' => [array_fill_keys(self::CAMPOS, true)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cargas')]
    public function test_formularios_con_datos_basura_no_dan_500(array $carga): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $malas = [];
        $total = 0;

        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            $metodos = array_diff($r->methods(), ['GET', 'HEAD']);
            if (!$metodos) continue;
            $dentro = false;
            foreach (self::PREFIJOS as $p) {
                if (str_starts_with($uri, $p)) { $dentro = true; break; }
            }
            if (!$dentro || str_contains($uri, 'logout') || str_contains($uri, 'cerrar') || ($uri === 'profile' && in_array('DELETE', $metodos, true))) continue;

            $url = '/' . preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $this->valorDe($m[1], $admin), $uri);
            $metodo = array_values($metodos)[0];
            $total++;
            try {
                $resp = $this->call($metodo === 'PUT' || $metodo === 'PATCH' ? 'POST' : $metodo, $url, $carga + ($metodo === 'PUT' ? ['_method' => 'PUT'] : ($metodo === 'PATCH' ? ['_method' => 'PATCH'] : [])));
                if ($resp->getStatusCode() >= 500) {
                    $ex = $resp->exception ? get_class($resp->exception) . ': ' . mb_substr($resp->exception->getMessage(), 0, 140) : '';
                    $malas[] = "{$resp->getStatusCode()} {$metodo} {$url} {$ex}";
                }
            } catch (\Throwable $e) {
                $malas[] = "EXC {$metodo} {$url} " . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 140);
            }
            // Cada ruta con un administrador vivo: se restablece por si la carga lo desactivó o cambió
            $admin->refresh();
            if ($admin->estado !== 'activo' || !$admin->role || $admin->role->nombre !== 'Administrador') {
                $admin->forceFill(['estado' => 'activo', 'role_id' => Role::where('nombre', 'Administrador')->value('id')])->save();
            }
            $this->actingAs($admin->fresh());
        }

        $this->assertGreaterThan(20, $total, 'Se esperaba recorrer varias rutas de escritura');
        $this->assertSame([], $malas, implode("\n", $malas));
    }
}
