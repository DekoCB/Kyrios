<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Venta;
use App\Models\SerieComprobante;
use App\Models\Sucursal;
use App\Services\SunatComprobanteService;
use Illuminate\Http\Request;

class FacturacionElectronicaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Administrador');
    }

    public function index(Request $request)
    {
        $query = Venta::with(['cliente', 'serieComprobante', 'sucursal', 'guiaRemision'])
            ->whereNotIn('tipo_comprobante', ['cotizacion'])
            ->orderByDesc('fecha');

        if ($request->filled('estado_sunat')) {
            $query->where('estado_sunat', $request->estado_sunat);
        }

        if ($request->filled('tipo_comprobante')) {
            $query->where('tipo_comprobante', $request->tipo_comprobante);
        }

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        if ($request->filled('con_guia')) {
            if ($request->con_guia === '1') {
                $query->whereHas('guiaRemision');
            } else {
                $query->whereDoesntHave('guiaRemision');
            }
        }

        if ($request->filled('estado_guia')) {
            $query->whereHas('guiaRemision', fn($q) => $q->where('estado', $request->estado_guia));
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->whereHas('cliente', fn($c) => $c->where('nombre', 'like', "%$buscar%")
                    ->orWhere('documento', 'like', "%$buscar%"))
                  ->orWhereHas('serieComprobante', fn($s) => $s->where('serie', 'like', "%$buscar%"));
            });
        }

        $comprobantes = $query->paginate(20)->withQueryString();

        $sucursales = Sucursal::orderBy('nombre')->get(['id', 'nombre']);

        $stats = [
            'total_emitidos'    => Venta::whereNotIn('tipo_comprobante', ['cotizacion'])->count(),
            'pendiente_envio'   => Venta::where('estado_sunat', 'pendiente_envio')->count(),
            'aceptados'         => Venta::where('estado_sunat', 'aceptado')->count(),
            'rechazados'        => Venta::where('estado_sunat', 'rechazado')->count(),
            'hoy'               => Venta::whereNotIn('tipo_comprobante', ['cotizacion'])
                                       ->whereDate('fecha', today())->count(),
        ];

        return view('facturacion.index', compact('comprobantes', 'sucursales', 'stats'));
    }

    public function series(Request $request)
    {
        $sucursales = Sucursal::orderBy('nombre')->get(['id', 'nombre']);

        $query = SerieComprobante::with('sucursal')->orderBy('sucursal_id')->orderBy('tipo_comprobante');

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        $series = $query->get();

        $tiposComprobante = SerieComprobante::TIPOS;

        return view('facturacion.series', compact('series', 'sucursales', 'tiposComprobante'));
    }

    public function storeSerie(Request $request)
    {
        $request->validate([
            'sucursal_id'       => 'required|exists:sucursales,id',
            'tipo_comprobante'  => 'required|string|max:5',
            'tipo_nombre'       => 'required|string|max:80',
            'serie'             => 'required|string|max:5',
            'correlativo_actual'=> 'required|integer|min:1',
            'formato_impresion' => 'required|in:A4,ticket,A5',
        ]);

        $existe = SerieComprobante::where('sucursal_id', $request->sucursal_id)
            ->where('serie', strtoupper($request->serie))
            ->exists();

        if ($existe) {
            return back()->with('error', 'Ya existe una serie con ese código para esa sucursal.');
        }

        SerieComprobante::create([
            'sucursal_id'        => $request->sucursal_id,
            'tipo_comprobante'   => $request->tipo_comprobante,
            'tipo_nombre'        => $request->tipo_nombre,
            'serie'              => strtoupper($request->serie),
            'correlativo_actual' => $request->correlativo_actual,
            'formato_impresion'  => $request->formato_impresion,
            'activo'             => true,
        ]);

        return back()->with('success', 'Serie creada exitosamente.');
    }

    public function updateSerie(Request $request, SerieComprobante $serie)
    {
        $request->validate([
            'tipo_nombre'        => 'required|string|max:80',
            'correlativo_actual' => 'required|integer|min:1',
            'formato_impresion'  => 'required|in:A4,ticket,A5',
            'activo'             => 'boolean',
        ]);

        $serie->update([
            'tipo_nombre'        => $request->tipo_nombre,
            'correlativo_actual' => $request->correlativo_actual,
            'formato_impresion'  => $request->formato_impresion,
            'activo'             => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Serie actualizada correctamente.');
    }

    public function destroySerie(SerieComprobante $serie)
    {
        if ($serie->ventas()->exists()) {
            return back()->with('error', 'No se puede eliminar: tiene comprobantes emitidos.');
        }

        $serie->delete();
        return back()->with('success', 'Serie eliminada.');
    }

    public function updateEstadoGuia(Request $request, Venta $venta)
    {
        $request->validate([
            'estado' => 'required|in:pendiente,en_transito,entregada,anulada',
        ]);

        $guia = $venta->guiaRemision;

        if (!$guia) {
            return back()->with('error', 'Esta venta no tiene guía de remisión asociada.');
        }

        $guia->update(['estado' => $request->estado]);

        return back()->with('success', 'Estado de guía actualizado a «' . $guia->estado_label . '».');
    }

    public function reenviar(Venta $venta)
    {
        if (!in_array($venta->estado_sunat, ['pendiente_envio', 'rechazado'])) {
            return back()->with('error', 'Este comprobante no puede ser reenviado en su estado actual.');
        }

        $venta->update(['estado_sunat' => 'pendiente_envio']);

        return back()->with('success', 'Comprobante marcado para reenvío a SUNAT.');
    }

    public function enviarSunat(Venta $venta)
    {
        if (!in_array($venta->estado_sunat, ['pendiente_envio', 'rechazado']) && $venta->tipo_comprobante !== 'cotizacion') {
            if ($venta->estado_sunat === 'aceptado') {
                return back()->with('error', 'Este comprobante ya fue aceptado por SUNAT.');
            }
        }

        $result = app(SunatComprobanteService::class)->enviar($venta);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function consultarSunat(Venta $venta)
    {
        if (!$venta->sunat_enviado_at) {
            return back()->with('error', 'Este comprobante no ha sido enviado a SUNAT.');
        }

        $result = app(SunatComprobanteService::class)->consultarEstado($venta);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function configuracion()
    {
        $sucursales = Sucursal::with('series')->orderBy('nombre')->get();

        return view('facturacion.configuracion', compact('sucursales'));
    }

    public function downloadXml(Venta $venta)
    {
        $venta->load(['cliente', 'serieComprobante', 'detalles.producto', 'detalles.variante']);
        $empresa = Empresa::instancia();

        $tipoDoc  = match($venta->tipo_comprobante) {
            'factura'        => '01',
            'boleta'         => '03',
            'nota_credito'   => '07',
            'nota_debito'    => '08',
            default          => '03',
        };

        $serie       = $venta->serieComprobante?->serie ?? 'B001';
        $correlativo = str_pad($venta->correlativo ?? 1, 8, '0', STR_PAD_LEFT);
        $id          = "{$serie}-{$correlativo}";
        $fecha       = $venta->fecha->format('Y-m-d');

        $subtotalBase = (float) $venta->subtotal;
        $igvTotal     = (float) $venta->igv;
        $totalPagar   = (float) $venta->total;

        $tipoDocCliente = match(strtolower($venta->cliente?->tipo_documento ?? 'dni')) {
            'ruc'       => '6',
            'dni'       => '1',
            'pasaporte' => '7',
            'ce'        => '4',
            default     => '0',
        };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"' . "\n";
        $xml .= '  xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"' . "\n";
        $xml .= '  xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"' . "\n";
        $xml .= '  xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">' . "\n";

        $xml .= "  <ext:UBLExtensions><ext:UBLExtension><ext:ExtensionContent/></ext:UBLExtension></ext:UBLExtensions>\n";
        $xml .= "  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n";
        $xml .= "  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n";
        $xml .= "  <cbc:ID>{$id}</cbc:ID>\n";
        $xml .= "  <cbc:IssueDate>{$fecha}</cbc:IssueDate>\n";
        $xml .= "  <cbc:InvoiceTypeCode listID=\"{$tipoDoc}\">{$tipoDoc}</cbc:InvoiceTypeCode>\n";
        $xml .= "  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n";

        // Emisor
        $xml .= "  <cac:AccountingSupplierParty>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID schemeID=\"6\">" . e($empresa?->ruc ?? '') . "</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName>" . e($empresa?->razon_social ?? '') . "</cbc:RegistrationName>\n";
        $xml .= "        <cac:RegistrationAddress>\n";
        $xml .= "          <cbc:AddressLine><cbc:Line>" . e($empresa?->direccion ?? '') . "</cbc:Line></cbc:AddressLine>\n";
        $xml .= "        </cac:RegistrationAddress>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:AccountingSupplierParty>\n";

        // Receptor
        $xml .= "  <cac:AccountingCustomerParty>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID schemeID=\"{$tipoDocCliente}\">" . e($venta->cliente?->numero_documento ?? '-') . "</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName>" . e($venta->cliente?->nombre ?? 'CLIENTE VARIOS') . "</cbc:RegistrationName>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:AccountingCustomerParty>\n";

        // IGV
        $xml .= "  <cac:TaxTotal>\n";
        $xml .= "    <cbc:TaxAmount currencyID=\"PEN\">" . number_format($igvTotal, 4, '.', '') . "</cbc:TaxAmount>\n";
        $xml .= "    <cac:TaxSubtotal>\n";
        $xml .= "      <cbc:TaxableAmount currencyID=\"PEN\">" . number_format($subtotalBase, 4, '.', '') . "</cbc:TaxableAmount>\n";
        $xml .= "      <cbc:TaxAmount currencyID=\"PEN\">" . number_format($igvTotal, 4, '.', '') . "</cbc:TaxAmount>\n";
        $xml .= "      <cac:TaxCategory>\n";
        $xml .= "        <cbc:ID schemeID=\"UN/ECE 5305\">S</cbc:ID>\n";
        $xml .= "        <cbc:Percent>18</cbc:Percent>\n";
        $xml .= "        <cac:TaxScheme><cbc:ID>1000</cbc:ID><cbc:Name>IGV</cbc:Name><cbc:TaxTypeCode>VAT</cbc:TaxTypeCode></cac:TaxScheme>\n";
        $xml .= "      </cac:TaxCategory>\n";
        $xml .= "    </cac:TaxSubtotal>\n";
        $xml .= "  </cac:TaxTotal>\n";

        // Totales
        $xml .= "  <cac:LegalMonetaryTotal>\n";
        $xml .= "    <cbc:LineExtensionAmount currencyID=\"PEN\">" . number_format($subtotalBase, 4, '.', '') . "</cbc:LineExtensionAmount>\n";
        $xml .= "    <cbc:TaxInclusiveAmount currencyID=\"PEN\">" . number_format($totalPagar, 4, '.', '') . "</cbc:TaxInclusiveAmount>\n";
        $xml .= "    <cbc:PayableAmount currencyID=\"PEN\">" . number_format($totalPagar, 4, '.', '') . "</cbc:PayableAmount>\n";
        $xml .= "  </cac:LegalMonetaryTotal>\n";

        // Líneas de detalle
        foreach ($venta->detalles as $i => $det) {
            $linea   = $i + 1;
            $nombre  = $det->producto?->nombre ?? 'Producto';
            if ($det->variante?->nombre_completo) {
                $nombre .= ' - ' . $det->variante->nombre_completo;
            }
            $precioUnitSinIgv = round((float) $det->precio_unitario, 4);
            $precioConIgv     = round((float) ($det->precio_con_igv ?? $det->precio_unitario * 1.18), 4);
            $baseImponible    = round((float) $det->subtotal, 4);
            $totalLinea       = round((float) ($det->subtotal_con_igv ?? $det->subtotal * 1.18), 4);
            $igvLinea         = round($totalLinea - $baseImponible, 4);

            $xml .= "  <cac:InvoiceLine>\n";
            $xml .= "    <cbc:ID>{$linea}</cbc:ID>\n";
            $xml .= "    <cbc:InvoicedQuantity unitCode=\"NIU\">{$det->cantidad}</cbc:InvoicedQuantity>\n";
            $xml .= "    <cbc:LineExtensionAmount currencyID=\"PEN\">" . number_format($baseImponible, 4, '.', '') . "</cbc:LineExtensionAmount>\n";
            $xml .= "    <cac:TaxTotal>\n";
            $xml .= "      <cbc:TaxAmount currencyID=\"PEN\">" . number_format($igvLinea, 4, '.', '') . "</cbc:TaxAmount>\n";
            $xml .= "      <cac:TaxSubtotal>\n";
            $xml .= "        <cbc:TaxableAmount currencyID=\"PEN\">" . number_format($baseImponible, 4, '.', '') . "</cbc:TaxableAmount>\n";
            $xml .= "        <cbc:TaxAmount currencyID=\"PEN\">" . number_format($igvLinea, 4, '.', '') . "</cbc:TaxAmount>\n";
            $xml .= "        <cac:TaxCategory>\n";
            $xml .= "          <cbc:ID schemeID=\"UN/ECE 5305\">S</cbc:ID>\n";
            $xml .= "          <cbc:Percent>18</cbc:Percent>\n";
            $xml .= "          <cac:TaxScheme><cbc:ID>1000</cbc:ID><cbc:Name>IGV</cbc:Name><cbc:TaxTypeCode>VAT</cbc:TaxTypeCode></cac:TaxScheme>\n";
            $xml .= "        </cac:TaxCategory>\n";
            $xml .= "      </cac:TaxSubtotal>\n";
            $xml .= "    </cac:TaxTotal>\n";
            $xml .= "    <cac:Item>\n";
            $xml .= "      <cbc:Description>" . e($nombre) . "</cbc:Description>\n";
            $xml .= "    </cac:Item>\n";
            $xml .= "    <cac:Price>\n";
            $xml .= "      <cbc:PriceAmount currencyID=\"PEN\">" . number_format($precioUnitSinIgv, 4, '.', '') . "</cbc:PriceAmount>\n";
            $xml .= "    </cac:Price>\n";
            $xml .= "  </cac:InvoiceLine>\n";
        }

        $xml .= "</Invoice>\n";

        $filename = "{$id}.xml";

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
