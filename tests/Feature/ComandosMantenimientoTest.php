<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoCodigoBarras;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * productos:importar-imagenes (carpeta de fotos de los clientes) y kyrios:purgar-datos.
 */
class ComandosMantenimientoTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->carpeta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fotos_' . uniqid();
        File::makeDirectory($this->carpeta);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'codigo_fabricante' => '25-0573-N3-B8', 'codigo_excel' => 'KLU01-00003', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002',
            'codigo_fabricante' => '25-0573-N3-M1', 'estado' => 'activo']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    private function foto(string $relativa): void
    {
        $ruta = $this->carpeta . DIRECTORY_SEPARATOR . $relativa;
        File::ensureDirectoryExists(dirname($ruta));
        // Cada archivo tiene un contenido distinto (el importador no vuelve a cargar fotos idénticas)
        $im = imagecreatetruecolor(4 + crc32($relativa) % 60, 4);
        imagepng($im, $ruta);
        imagedestroy($im);
    }

    private function importar(array $opciones = [])
    {
        return $this->artisan('productos:importar-imagenes', ['carpeta' => $this->carpeta] + $opciones);
    }

    // ── Imágenes por carpeta ──────────────────────────────────────────────

    public function test_una_carpeta_inexistente_falla(): void
    {
        $this->artisan('productos:importar-imagenes', ['carpeta' => $this->carpeta . '_no'])->assertFailed();
    }

    public function test_asocia_por_los_tres_codigos_con_sufijo_de_orden(): void
    {
        $this->foto('KY-LU2501-0001.png');          // código Kyrios → imagen 1
        $this->foto('KY-LU2501-0001_2.png');        // sufijo _2 → imagen 2
        $this->foto('25-0573-N3-M1.png');           // código del fabricante (lleva guiones)
        $this->foto('KLU01-00003 (3).png');         // código del Excel con " (3)"

        $this->importar()->assertSuccessful();

        $this->assertSame([1, 2, 3], $this->v1->imagenes()->pluck('orden')->all());
        $this->assertSame([1], $this->v2->imagenes()->pluck('orden')->all());
        foreach (ProductoImagen::all() as $img) {
            Storage::disk('public')->assertExists($img->ruta);
        }
    }

    public function test_una_carpeta_con_el_codigo_ordena_por_nombre_y_respeta_el_maximo_de_seis(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g'] as $n) {
            $this->foto("KY-LU2501-0002/foto-{$n}.png");
        }

        $this->importar()->assertSuccessful();

        $this->assertSame(6, $this->v2->imagenes()->count(), 'la séptima se descarta');
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->v2->imagenes()->pluck('orden')->all());
        $this->assertSame(1, $this->v2->imagenes()->where('es_principal', true)->count());
    }

    public function test_los_archivos_con_codigo_desconocido_no_se_cargan(): void
    {
        $this->foto('desconocido.png');
        $this->foto('KY-LU2501-0001.png');

        $this->importar()->assertSuccessful();

        $this->assertSame(1, ProductoImagen::count());
    }

    public function test_un_codigo_de_barras_asociado_tambien_identifica_la_variante(): void
    {
        ProductoCodigoBarras::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'codigo_barras' => '7501234567890']);
        $this->foto('7501234567890.png');

        $this->importar()->assertSuccessful();

        $this->assertSame(1, $this->v2->imagenes()->count());
    }

    public function test_dry_run_no_carga_nada(): void
    {
        $this->foto('KY-LU2501-0001.png');

        $this->importar(['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, ProductoImagen::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_sin_reemplazar_completa_los_lugares_libres_y_con_reemplazar_empieza_de_cero(): void
    {
        $this->foto('KY-LU2501-0001.png');
        $this->importar()->assertSuccessful();
        $primera = $this->v1->imagenes()->first();

        $this->foto('KY-LU2501-0001_2.png');
        $this->importar()->assertSuccessful();
        $this->assertSame(2, $this->v1->imagenes()->count(), 'no duplica la que ya estaba ni pisa su orden');

        $this->importar(['--reemplazar' => true])->assertSuccessful();
        $this->assertSame(2, $this->v1->imagenes()->count(), 'reemplazar vuelve a cargar todo, sin acumular');
        $this->assertSame(1, $this->v1->imagenes()->where('es_principal', true)->count());
    }

    // ── Purga de datos ────────────────────────────────────────────────────

    public function test_la_purga_vacia_los_datos_de_negocio_y_conserva_usuarios_y_roles(): void
    {
        $rol = Role::firstOrCreate(['nombre' => 'Administración']);
        $almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'principal', 'estado' => 'activo']);
        $user = User::factory()->create(['role_id' => $rol->id, 'estado' => 'activo', 'almacen_id' => $almacen->id]);
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Cliente', 'estado' => 'activo']);
        StockAlmacen::fila($this->producto->id, $almacen->id, $this->v1->id)->increment('cantidad', 5);
        $rolesAntes = Role::count();

        $this->artisan('kyrios:purgar-datos', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, ProductoVariante::count());
        $this->assertSame(0, StockAlmacen::count());
        $this->assertSame(0, Almacen::count());
        $this->assertSame(0, Cliente::count());

        $this->assertSame(1, User::count(), 'los usuarios se conservan');
        $this->assertNull($user->fresh()->almacen_id, 'sin el almacén que ya no existe');
        $this->assertSame($rolesAntes, Role::count(), 'los roles se conservan');
    }

    public function test_la_purga_vuelve_a_sembrar_los_catalogos_base(): void
    {
        $this->artisan('kyrios:purgar-datos', ['--force' => true])->assertSuccessful();

        $this->assertSame(30, DB::table('marcas')->count(), 'las 30 marcas del PDF');
        $this->assertSame(16, DB::table('tipos_producto')->count(), '15 del PDF + «Sin tipo»');
        $this->assertSame(17, DB::table('tipos_luminaria')->count());
        $this->assertGreaterThan(0, DB::table('motivos_movimiento')->count());
        $this->assertSame('01', DB::table('marcas')->where('nombre', 'LEDS-C4')->value('codigo'));
    }

    public function test_sin_confirmacion_la_purga_no_borra_nada(): void
    {
        $this->artisan('kyrios:purgar-datos')->expectsConfirmation(
            "Esto BORRA todos esos datos de '" . DB::getDatabaseName() . "' y no se puede deshacer. ¿Hizo un respaldo y desea continuar?",
            'no'
        )->assertSuccessful();

        $this->assertSame(1, Producto::count());
    }
}
