<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas Masivas IMEI - {{ config('app.name') }}</title>
    <style>
        /* Reset para impresión */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: white;
            padding: 0.5cm;
        }
        
        /* Configuración de hoja */
        @page {
            size: A4;
            margin: 0.5cm;
        }
        
        /* Grid de etiquetas - 3 columnas x 8 filas = 24 etiquetas por hoja */
        .etiquetas-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.3cm;
            page-break-inside: avoid;
        }
        
        /* Estilo de cada etiqueta */
        .etiqueta {
            width: 100%;
            height: 2.5cm; /* Alto estándar para etiquetas adhesivas */
            border: 1px solid #000;
            padding: 0.2cm;
            position: relative;
            background: white;
            border-radius: 3px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        /* QR en esquina superior derecha */
        .qr {
            position: absolute;
            top: 0.15cm;
            right: 0.15cm;
            width: 0.8cm;
            height: 0.8cm;
        }
        
        .qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        /* Información del equipo */
        .info {
            margin-right: 1cm; /* Espacio para el QR */
            font-size: 8px;
            line-height: 1.2;
        }
        
        .imei {
            font-size: 10px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin-bottom: 0.1cm;
            color: #000;
            word-break: break-all;
        }
        
        .producto {
            font-size: 8px;
            font-weight: 600;
            margin-bottom: 0.05cm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .detalles {
            font-size: 7px;
            color: #333;
            margin-bottom: 0.05cm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .marca-modelo {
            font-size: 7px;
            color: #666;
        }
        
        /* Estado en la parte inferior */
        .estado {
            position: absolute;
            bottom: 0.15cm;
            left: 0.2cm;
            right: 0.2cm;
            font-size: 6px;
            text-align: center;
            color: #000;
            border-top: 1px dashed #ccc;
            padding-top: 0.05cm;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        /* Colores de estado */
        .estado-en-stock { color: #059669; }
        .estado-reservado { color: #d97706; }
        .estado-vendido { color: #dc2626; }
        .estado-garantia { color: #2563eb; }
        .estado-devuelto { color: #ea580c; }
        .estado-reemplazado { color: #7c3aed; }
        
        /* Cabecera de la hoja */
        .hoja-header {
            margin-bottom: 0.5cm;
            padding-bottom: 0.3cm;
            border-bottom: 2px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .hoja-header h1 {
            font-size: 14px;
            font-weight: bold;
        }
        
        .hoja-header .fecha {
            font-size: 10px;
            color: #666;
        }
        
        /* Pie de página */
        .hoja-footer {
            margin-top: 0.5cm;
            padding-top: 0.3cm;
            border-top: 1px solid #ccc;
            font-size: 8px;
            text-align: center;
            color: #666;
        }
        
        /* Estilos para impresión */
        @media print {
            body {
                padding: 0;
            }
            .etiqueta {
                border: 1px solid #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
        }
        
        /* Si no hay IMEIs */
        .sin-imeis {
            text-align: center;
            padding: 2cm;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    @if($imeis->isEmpty())
        <div class="sin-imeis">
            <p>No hay IMEIs seleccionados para imprimir</p>
        </div>
    @else
        <!-- Cabecera (no se imprime) -->
        <div class="hoja-header no-print">
            <h1>
                <i class="fas fa-tags"></i>
                Etiquetas de IMEI - {{ count($imeis) }} equipos
            </h1>
            <div class="fecha">
                {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>

        <!-- Botones de impresión (no se imprimen) -->
        <div class="no-print" style="margin-bottom: 1cm; text-align: center;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                <i class="fas fa-print"></i> Imprimir Etiquetas ({{ count($imeis) }})
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>

        <!-- Grid de etiquetas -->
        <div class="etiquetas-grid">
            @foreach($imeis as $imei)
                <div class="etiqueta">
                    <!-- QR Code -->
                    <div class="qr">
                        @if($imei->qr_path)
                            <img src="{{ Storage::url($imei->qr_path) }}" alt="QR">
                        @else
                            <div style="width: 100%; height: 100%; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 6px;">
                                SIN QR
                            </div>
                        @endif
                    </div>

                    <!-- Información -->
                    <div class="info">
                        <div class="imei">{{ $imei->codigo_imei }}</div>
                        
                        @php
                            $producto = $imei->producto;
                            $marca = $producto->marca->nombre ?? '';
                            $modelo = $producto->modelo->nombre ?? '';
                            $color = $imei->color->nombre ?? $producto->color->nombre ?? '';
                        @endphp
                        
                        <div class="producto" title="{{ $producto->nombre }}">
                            {{ Str::limit($producto->nombre, 30) }}
                        </div>
                        
                        @if($marca || $modelo)
                            <div class="detalles">
                                {{ trim("$marca $modelo") }}
                            </div>
                        @endif
                        
                        @if($color)
                            <div class="detalles">
                                Color: {{ $color }}
                            </div>
                        @endif
                        
                        @if($imei->serie)
                            <div class="detalles">
                                Serie: {{ $imei->serie }}
                            </div>
                        @endif
                        
                        <div class="detalles">
                            {{ $producto->codigo ?? '' }}
                        </div>
                    </div>

                    <!-- Estado -->
                    <div class="estado estado-{{ str_replace('_', '-', $imei->estado_imei) }}">
                        {{ strtoupper(str_replace('_', ' ', $imei->estado_imei)) }}
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Información de la impresión (no se imprime) -->
        <div class="hoja-footer no-print">
            <p>
                Total: {{ count($imeis) }} etiquetas | 
                Hojas aproximadas: {{ ceil(count($imeis) / 24) }} (24 etiquetas por hoja)
            </p>
            <p style="margin-top: 5px; font-size: 7px;">
                Configura tu impresora para papel de etiquetas adhesivas (tamaño: 2.5cm x 3.8cm aprox)
            </p>
        </div>
    @endif

    <script>
        // Auto-impresión (opcional)
        window.onload = function() {
            // Descomentar la siguiente línea si quieres que imprima automáticamente
            // window.print();
        };
    </script>
</body>
</html>