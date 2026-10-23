<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CodigoBarrasService
{
    /**
     * Prefijos para diferentes tipos de productos
     */
    const PREFIJOS = [
        'accesorio' => '300',
        'cargador' => '310',
        'case' => '320',
        'repuesto' => '330',
        'otros' => '400'
    ];

    /**
     * Generar código de barras único para un producto
     */
    public function generarCodigoUnico(Producto $producto = null, string $tipo = null): string
    {
        $tipo = $tipo ?? ($producto ? 'accesorio' : 'otros');
        $prefijo = self::PREFIJOS[$tipo] ?? '400';
        
        do {
            // Formato: PREFIJO + TIMESTAMP(8) + RANDOM(4) + DIGITO_VERIFICADOR
            $timestamp = substr(time(), -8);
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $base = $prefijo . $timestamp . $random;
            $digitoVerificador = $this->calcularDigitoVerificador($base);
            $codigo = $base . $digitoVerificador;
            
            // Verificar que no exista ya en la base de datos
            $existe = $producto 
                ? Producto::where('codigo_barras', $codigo)->where('id', '!=', $producto->id)->exists()
                : Producto::where('codigo_barras', $codigo)->exists();
                
        } while ($existe);
        
        return $codigo;
    }

    /**
     * Generar código de barras basado en el nombre del producto
     */
    public function generarCodigoDesdeNombre(string $nombre, string $tipo = 'otros'): string
    {
        $prefijo = self::PREFIJOS[$tipo] ?? '400';
        
        // Tomar las primeras letras del nombre (hasta 4 caracteres)
        $letras = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 4));
        if (strlen($letras) < 2) {
            $letras = 'PROD';
        }
        
        $timestamp = substr(time(), -6);
        $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $base = $prefijo . $letras . $timestamp . $random;
        
        return $base . $this->calcularDigitoVerificador($base);
    }

    /**
     * Generar código EAN-13 válido
     */
    public function generarEAN13(string $pais = '775'): string
    {
        // Perú: 775 (código de país)
        $codigoBase = $pais . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        $digito = $this->calcularDigitoEAN13($codigoBase);
        
        return $codigoBase . $digito;
    }

    /**
     * Generar código QR con información del producto
     */
    public function generarCodigoQR(Producto $producto): array
    {
        $data = [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'codigo' => $producto->codigo_barras ?? $this->generarCodigoUnico($producto),
            'precio' => $producto->precio_venta ?? 0,
            'stock' => $producto->stock_actual ?? 0,
            'url' => route('productos.show', $producto),
        ];
        
        // Aquí podrías usar una librería como simplesoftwareio/simple-qrcode
        // para generar la imagen QR
        // \QrCode::size(200)->generate(json_encode($data));
        
        return [
            'data' => $data,
            'string' => json_encode($data)
        ];
    }

    /**
     * Validar si un código de barras es válido según formato
     */
    public function validarCodigo(string $codigo, string $tipo = 'EAN13'): bool
    {
        $codigo = preg_replace('/[^0-9]/', '', $codigo);
        
        switch ($tipo) {
            case 'EAN13':
                return $this->validarEAN13($codigo);
            case 'EAN8':
                return $this->validarEAN8($codigo);
            case 'CODE128':
                return $this->validarCODE128($codigo);
            default:
                return strlen($codigo) >= 8 && strlen($codigo) <= 14;
        }
    }

    /**
     * Calcular dígito verificador (módulo 10)
     */
    private function calcularDigitoVerificador(string $codigo): int
    {
        $digitos = array_reverse(array_map('intval', str_split($codigo)));
        $suma = 0;
        
        foreach ($digitos as $index => $digito) {
            if ($index % 2 == 0) {
                $suma += $digito * 3;
            } else {
                $suma += $digito;
            }
        }
        
        return (10 - ($suma % 10)) % 10;
    }

    /**
     * Calcular dígito verificador EAN-13
     */
    private function calcularDigitoEAN13(string $codigo): int
    {
        $digitos = array_map('intval', str_split($codigo));
        $suma = 0;
        
        foreach ($digitos as $index => $digito) {
            if ($index % 2 == 0) {
                $suma += $digito;
            } else {
                $suma += $digito * 3;
            }
        }
        
        $resto = $suma % 10;
        return $resto == 0 ? 0 : 10 - $resto;
    }

    /**
     * Validar EAN-13
     */
    private function validarEAN13(string $codigo): bool
    {
        if (strlen($codigo) != 13 || !ctype_digit($codigo)) {
            return false;
        }
        
        $digitoCalculado = $this->calcularDigitoEAN13(substr($codigo, 0, 12));
        return $digitoCalculado == substr($codigo, -1);
    }

    /**
     * Validar EAN-8
     */
    private function validarEAN8(string $codigo): bool
    {
        if (strlen($codigo) != 8 || !ctype_digit($codigo)) {
            return false;
        }
        
        $digitos = array_map('intval', str_split(substr($codigo, 0, 7)));
        $suma = 0;
        
        foreach ($digitos as $index => $digito) {
            if ($index % 2 == 0) {
                $suma += $digito * 3;
            } else {
                $suma += $digito;
            }
        }
        
        $digitoCalculado = (10 - ($suma % 10)) % 10;
        return $digitoCalculado == substr($codigo, -1);
    }

    /**
     * Validar CODE128 (simplificado)
     */
    private function validarCODE128(string $codigo): bool
    {
        // CODE128 puede tener letras y números
        return strlen($codigo) >= 4 && strlen($codigo) <= 20;
    }

    /**
     * Generar código para un lote de productos
     */
    public function generarCodigosLote(int $cantidad, string $tipo = 'otros'): array
    {
        $codigos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $codigos[] = $this->generarCodigoUnico(null, $tipo);
        }
        return $codigos;
    }


    /**
     * Formatear código de barras para impresión
     */
    public function formatearParaImpresion(string $codigo, string $formato = 'texto'): string
    {
        switch ($formato) {
            case 'ean13':
                // Formato: 1234567890123 -> 123456 789012 3
                return substr($codigo, 0, 6) . ' ' . substr($codigo, 6, 6) . ' ' . substr($codigo, -1);
            default:
                return $codigo;
        }
    }

    /**
     * Decodificar información de un código de barras
     */
    public function decodificarCodigo(string $codigo): array
    {
        $prefijo = substr($codigo, 0, 3);
        
        // Identificar tipo por prefijo
        $tipo = array_search($prefijo, self::PREFIJOS);
        
        return [
            'codigo' => $codigo,
            'tipo' => $tipo ?: 'desconocido',
            'prefijo' => $prefijo,
            'timestamp' => substr($codigo, 3, 8),
            'aleatorio' => substr($codigo, 11, 4),
            'digito' => substr($codigo, -1),
            'valido' => $this->validarCodigo($codigo),
        ];
    }

    /**
     * Sincronizar códigos de barras con productos
     */
    public function sincronizarProductosSinCodigo(): int
    {
        $productos = Producto::whereNull('codigo_barras')
            ->orWhere('codigo_barras', '')
            ->get();
            
        $contador = 0;
        
        foreach ($productos as $producto) {
            $codigo = $this->generarCodigoUnico($producto);
            $producto->update(['codigo_barras' => $codigo]);
            $contador++;
            
            Log::info('Código de barras generado automáticamente', [
                'producto_id' => $producto->id,
                'codigo' => $codigo
            ]);
        }
        
        return $contador;
    }

    /**
     * Obtener estadísticas de códigos de barras
     */
    public function obtenerEstadisticas(): array
    {
        $totalProductos = Producto::count();
        $conCodigo = Producto::whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->count();
        
        return [
            'total_productos' => $totalProductos,
            'con_codigo' => $conCodigo,
            'sin_codigo' => $totalProductos - $conCodigo,
            'porcentaje_cobertura' => $totalProductos > 0 
                ? round(($conCodigo / $totalProductos) * 100, 2) 
                : 0,
            'por_tipo' => Producto::selectRaw('tipo_producto, COUNT(*) as total, COUNT(codigo_barras) as con_codigo')
                ->groupBy('tipo_producto')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->tipo_producto => [
                        'total' => $item->total,
                        'con_codigo' => $item->con_codigo,
                        'porcentaje' => $item->total > 0 
                            ? round(($item->con_codigo / $item->total) * 100, 2) 
                            : 0
                    ]];
                }),
        ];
    }
}