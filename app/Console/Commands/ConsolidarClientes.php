<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\ClienteProyecto;
use App\Models\ClienteSeguimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consolida la base de clientes con el Excel «Base de datos - Consolidado»:
 *  1. fusiona los duplicados evidentes,
 *  2. pone a cada cliente el ID que tiene en el Excel (KYR1 → KYR001); quien no lo tenía queda sin ID; crea los que falten,
 *  3. importa el seguimiento comercial y los proyectos de cada fila,
 *  4. traduce los códigos numéricos del medio de contacto (6, 2…) a su significado.
 * Es repetible: el seguimiento y los proyectos importados se reemplazan; las fusiones ya hechas no se repiten.
 */
class ConsolidarClientes extends Command
{
    protected $signature = 'clientes:consolidar {archivo : JSON con la clave "filas"} {--dry-run : Solo informa, no escribe} {--reporte= : Archivo donde dejar el detalle}';

    protected $description = 'Fusiona duplicados, aplica los IDs del Excel e importa seguimiento y proyectos';

    /** Leyenda del «MEDIO DE CONTACTO / REFERIDO POR (6 opciones)» del Excel. */
    private const MEDIOS = [1 => 'Referido', 2 => 'Cliente antiguo', 3 => 'Otro', 4 => 'Red social', 5 => 'Web', 6 => 'Vitrina'];

    private array $reporte = [];
    private bool $seco = false;

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        $datos = is_file($archivo) ? json_decode(file_get_contents($archivo), true) : null;
        if (!is_array($datos['filas'] ?? null)) {
            $this->error('No se pudo leer el JSON (clave "filas").');
            return self::FAILURE;
        }
        $this->seco = (bool) $this->option('dry-run');
        $filas = array_values(array_filter($datos['filas'], fn ($f) => empty($f['sin_nombre'])));
        $sinNombre = array_filter($datos['filas'], fn ($f) => !empty($f['sin_nombre']));

        $this->info(($this->seco ? '[SIMULACIÓN] ' : '') . 'Clientes en el sistema: ' . Cliente::count() . ' · filas del Excel: ' . count($filas));

        DB::beginTransaction();
        try {
            $fusionados = $this->fusionarDuplicados();
            $this->info("Fusiones de duplicados: {$fusionados}");

            [$asignaciones, $creados, $adicionales] = $this->emparejar($filas);
            $this->info('Filas emparejadas: ' . count($asignaciones) . ' · clientes nuevos creados: ' . $creados . ' · IDs repetidos en el Excel: ' . $adicionales);

            $unidos = $this->unirEmpresas($filas, $asignaciones);
            $this->info("Empresas unidas a su contacto (un solo cliente): {$unidos}");

            $this->asignarCodigos($filas, $asignaciones);
            [$seg, $proy] = $this->importarSeguimientoYProyectos($filas, $asignaciones);
            $this->info("Seguimientos importados: {$seg} · proyectos importados: {$proy}");

            $this->info('Medios de contacto traducidos: ' . $this->limpiarOrigen());
            foreach ($sinNombre as $f) {
                $this->reporte[] = 'FILA SIN NOMBRE (no importada): fila ' . $f['fila'] . ' del Excel';
            }

            $this->seco ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Falló y se deshizo todo: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
            return self::FAILURE;
        }

        if ($ruta = $this->option('reporte')) {
            file_put_contents($ruta, implode(PHP_EOL, $this->reporte));
            $this->line("Detalle en {$ruta}");
        }
        $this->info($this->seco ? 'Simulación terminada: no se cambió nada.' : 'Listo.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ utilidades de texto

    private function norm(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;
        $s = preg_replace('/\p{Mn}+/u', '', $s);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    /** @return string[] */
    private function tokens(?string $s): array
    {
        $t = array_values(array_filter(explode(' ', $this->norm($s)), fn ($x) => $x !== '' && !in_array($x, ['de', 'del', 'la', 'las', 'los', 'y', 'e'], true)));

        return $t;
    }

    private function tokenParecido(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (min(strlen($a), strlen($b)) < 6) {
            return false;
        }
        // «Mario» y «Maria» son personas distintas: no se tratan como la misma palabra
        if (strlen($a) === strlen($b) && substr($a, 0, -1) === substr($b, 0, -1) && in_array(substr($a, -1) . substr($b, -1), ['ao', 'oa', 'ae', 'ea', 'oe', 'eo'], true)) {
            return false;
        }

        return levenshtein($a, $b) <= (max(strlen($a), strlen($b)) >= 9 ? 2 : 1);
    }

    /** ¿Cada token de $corto aparece (igual o casi igual) en $largo, sin repetir? */
    private function contenido(array $corto, array $largo): bool
    {
        if (!$corto) {
            return false;
        }
        foreach ($corto as $t) {
            $k = null;
            foreach ($largo as $i => $u) {
                if ($this->tokenParecido($t, $u)) {
                    $k = $i;
                    break;
                }
            }
            if ($k === null) {
                return false;
            }
            unset($largo[$k]);
        }

        return true;
    }

    private function esDifusion(object $c): bool
    {
        return $c->listas_difusion && !$c->telefono && !$c->email && !$c->numero_documento && str_starts_with((string) $c->created_at, '2026-10-29');
    }

    private function asesorLimpio(?string $s): ?string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return null;
        }
        $s = trim(preg_replace('/^(P|C)\d?\s+|^PE\s+/iu', '', $s));

        return $s === '' ? null : Str::title(mb_strtolower($s));
    }

    // ------------------------------------------------------------------ 1. fusionar duplicados

    private function fusionarDuplicados(): int
    {
        $total = 0;
        for ($pasada = 0; $pasada < 4; $pasada++) {
            $n = $this->pasadaFusion();
            $total += $n;
            if ($n === 0) {
                break;
            }
        }
        // los «ambiguos» de pasadas intermedias que se resolvieron ya no son ambiguos
        $this->reporte = array_values(array_unique($this->reporte));

        return $total;
    }

    private function pasadaFusion(): int
    {
        $cuenta = 0;
        $todos = DB::table('clientes')->orderBy('id')->get()->keyBy('id');
        $vivos = $todos->all();

        $mismoDoc = fn ($a, $b) => !($a->numero_documento && $b->numero_documento && $a->numero_documento !== $b->numero_documento);
        $fuerte = fn ($c) => ($c->numero_documento ? 100 : 0) + ($c->telefono ? 10 : 0) + ($c->email ? 10 : 0) + ($c->segmento ? 5 : 0) + ($c->asesor ? 5 : 0) + mb_strlen((string) $c->nombre) / 10;

        $fusionar = function ($sobrevive, $pierde, string $motivo) use (&$vivos, &$cuenta) {
            $this->reporte[] = "FUSIÓN ({$motivo}): «{$pierde->nombre}» ({$pierde->codigo}) → «{$sobrevive->nombre}» ({$sobrevive->codigo})";
            $this->fusionarDos($sobrevive->id, $pierde->id);
            unset($vivos[$pierde->id]);
            $cuenta++;
        };

        // a) mismo nombre exacto (sin tildes ni mayúsculas) y sin documentos contradictorios
        $porNombre = [];
        foreach ($vivos as $c) {
            if (!$this->esDifusion($c)) {
                $t = $this->tokens($c->nombre);
                sort($t);
                $porNombre[implode(' ', $t)][] = $c;
            }
        }
        foreach ($porNombre as $nombre => $grupo) {
            if ($nombre === '' || count($grupo) < 2) {
                continue;
            }
            usort($grupo, fn ($a, $b) => $fuerte($b) <=> $fuerte($a) ?: $a->id <=> $b->id);
            $base = $grupo[0];
            foreach (array_slice($grupo, 1) as $otro) {
                $unaPalabra = count(explode(' ', $nombre)) === 1;
                if ($unaPalabra && substr((string) $base->created_at, 0, 10) !== substr((string) $otro->created_at, 0, 10)) {
                    continue;
                }
                if ($mismoDoc($base, $otro) && isset($vivos[$otro->id])) {
                    $fusionar($base, $otro, 'mismo nombre');
                }
            }
        }

        // a2) mismas palabras con otra escritura («Gianoli» / «Gianolli»), sin documentos contradictorios
        $bloques = [];
        foreach ($vivos as $c) {
            if ($this->esDifusion($c)) {
                continue;
            }
            $t = $this->tokens($c->nombre);
            if (count($t) < 3) {
                continue;
            }
            sort($t);
            $bloques[substr($t[0], 0, 3) . count($t)][] = [$c, $t];
        }
        foreach ($bloques as $grupo) {
            for ($i = 0; $i < count($grupo); $i++) {
                for ($j = $i + 1; $j < count($grupo); $j++) {
                    [$a, $ta] = $grupo[$i];
                    [$b, $tb] = $grupo[$j];
                    if (!isset($vivos[$a->id]) || !isset($vivos[$b->id]) || !$mismoDoc($a, $b)) {
                        continue;
                    }
                    if ($this->contenido($ta, $tb) && $this->contenido($tb, $ta)) {
                        $fusionar($fuerte($b) > $fuerte($a) ? $b : $a, $fuerte($b) > $fuerte($a) ? $a : $b, 'misma escritura');
                    }
                }
            }
        }

        // b) misma fecha de registro y mismo asesor, nombre contenido en el otro («Gretna» ⊂ «Gretna Guerrero»)
        $porFecha = [];
        foreach ($vivos as $c) {
            if (!$this->esDifusion($c) && $c->created_at && !str_starts_with((string) $c->created_at, '2026-10-29')) {
                $porFecha[substr((string) $c->created_at, 0, 10)][] = $c;
            }
        }
        foreach ($porFecha as $grupo) {
            if (count($grupo) < 2) {
                continue;
            }
            usort($grupo, fn ($a, $b) => count($this->tokens($a->nombre)) <=> count($this->tokens($b->nombre)) ?: $a->id <=> $b->id);
            foreach ($grupo as $corto) {
                if (!isset($vivos[$corto->id])) {
                    continue;
                }
                $tc = $this->tokens($corto->nombre);
                $cand = [];
                foreach ($grupo as $largo) {
                    if ($largo->id !== $corto->id && isset($vivos[$largo->id]) && count($this->tokens($largo->nombre)) > count($tc)
                        && $this->norm($largo->asesor) === $this->norm($corto->asesor) && $mismoDoc($largo, $corto)
                        && $this->contenido($tc, $this->tokens($largo->nombre))) {
                        $cand[] = $largo;
                    }
                }
                if (count($cand) === 1) {
                    $fusionar($cand[0], $corto, 'misma fecha y asesor');
                } elseif (count($cand) > 1) {
                    $this->reporte[] = "AMBIGUO (no se fusionó): «{$corto->nombre}» ({$corto->codigo}) podría ser " . implode(' o ', array_map(fn ($c) => $c->codigo . ' ' . $c->nombre, array_slice($cand, 0, 4)));
                }
            }
        }

        // c) contactos de la lista de difusión (solo nombre) que corresponden, sin dudas, a un único cliente
        $reales = array_values(array_filter($vivos, fn ($c) => !$this->esDifusion($c)));
        $tokReales = [];
        foreach ($reales as $c) {
            $tokReales[$c->id] = $this->tokens($c->nombre);
        }
        foreach ($vivos as $d) {
            if (!$this->esDifusion($d) || !isset($vivos[$d->id])) {
                continue;
            }
            $t = $this->tokens($d->nombre);
            if (count($t) < 2) {
                continue;
            }
            $candidatos = [];
            foreach ($reales as $c) {
                if (isset($vivos[$c->id]) && $this->contenido($t, $tokReales[$c->id])) {
                    $candidatos[] = $c;
                }
            }
            if (count($candidatos) === 1) {
                $fusionar($candidatos[0], $d, 'lista de difusión');
            } elseif (count($candidatos) > 1) {
                $this->reporte[] = "AMBIGUO (no se fusionó): «{$d->nombre}» podría ser " . implode(' o ', array_map(fn ($c) => $c->codigo . ' ' . $c->nombre, array_slice($candidatos, 0, 4)));
            }
        }

        return $cuenta;
    }

    /** Pasa todo lo del cliente $pierde al cliente $sobrevive y lo elimina. */
    private function fusionarDos(int $sobrevive, int $pierde, bool $conservarNombre = false): void
    {
        $s = DB::table('clientes')->find($sobrevive);
        $p = DB::table('clientes')->find($pierde);

        $tablas = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::getDatabaseName())->where('COLUMN_NAME', 'cliente_id')->pluck('TABLE_NAME');
        foreach ($tablas as $t) {
            if (!str_starts_with($t, 'cliente_')) {
                DB::table($t)->where('cliente_id', $pierde)->update(['cliente_id' => $sobrevive]);
            }
        }

        $cambios = [];
        if (!$conservarNombre && mb_strtoupper($s->nombre) === $s->nombre && mb_strtoupper($p->nombre) !== $p->nombre && count($this->tokens($p->nombre)) >= count($this->tokens($s->nombre))) {
            $cambios['nombre'] = $p->nombre;
        }
        foreach ((array) $p as $campo => $valor) {
            if (in_array($campo, ['id', 'codigo', 'nombre', 'created_at', 'updated_at', 'listas_difusion', 'notas', 'numero_documento', 'tipo_documento'], true)) {
                continue;
            }
            if (($s->$campo === null || $s->$campo === '') && $valor !== null && $valor !== '') {
                $cambios[$campo] = $valor;
            }
        }
        $listas = array_values(array_unique(array_merge(json_decode((string) $s->listas_difusion, true) ?: [], json_decode((string) $p->listas_difusion, true) ?: [])));
        $cambios['listas_difusion'] = $listas ? json_encode($listas) : null;
        $cambios['notas'] = trim(($s->notas ?? '') . "\nFusionado con un registro duplicado («{$p->nombre}», {$p->codigo}).");
        if ($p->created_at && (!$s->created_at || $p->created_at < $s->created_at)) {
            $cambios['created_at'] = $p->created_at;
        }

        $doc = [$p->tipo_documento, $p->numero_documento];
        DB::table('clientes')->where('id', $pierde)->delete();
        if (!$s->numero_documento && $doc[1]) {
            $cambios['tipo_documento'] = $doc[0];
            $cambios['numero_documento'] = $doc[1];
        }
        DB::table('clientes')->where('id', $sobrevive)->update($cambios);
    }

    // ------------------------------------------------------------------ 2. emparejar filas con clientes

    /** @return array{0: array<int,int>, 1: int, 2: int} fila_excel => cliente_id, clientes creados, filas que repiten cliente */
    private function emparejar(array $filas): array
    {
        $clientes = DB::table('clientes')->get();
        $porId = $clientes->keyBy('id');
        $indiceNombre = [];
        $indiceCorreo = [];
        $indiceTel = [];
        $indiceDoc = [];
        foreach ($clientes as $c) {
            $indiceNombre[$this->norm($c->nombre)][] = $c->id;
            foreach ([$c->email, $c->email_empresa] as $e) {
                if ($e) {
                    $indiceCorreo[mb_strtolower(trim($e))][] = $c->id;
                }
            }
            if ($c->telefono) {
                $indiceTel[preg_replace('/\D/', '', $c->telefono)][] = $c->id;
            }
            foreach ([$c->numero_documento, $c->dni_complementario, $c->ruc_complementario] as $d) {
                if ($d) {
                    $indiceDoc[ltrim($d, '0')][] = $c->id;
                }
            }
        }
        $tokens = [];
        foreach ($clientes as $c) {
            $tokens[$c->id] = $this->tokens($c->nombre);
        }

        $elegir = function (array $ids, array $f) use ($porId) {
            $ids = array_values(array_unique($ids));
            $reales = array_values(array_filter($ids, fn ($i) => !$this->esDifusion($porId[$i])));
            $ids = $reales ?: $ids;
            usort($ids, function ($a, $b) use ($porId, $f) {
                $pa = (substr((string) $porId[$a]->created_at, 0, 10) === ($f['fecha'] ?? '-') ? 0 : 1);
                $pb = (substr((string) $porId[$b]->created_at, 0, 10) === ($f['fecha'] ?? '-') ? 0 : 1);

                return $pa <=> $pb ?: $a <=> $b;
            });

            return $ids[0] ?? null;
        };

        $asignaciones = [];
        $usados = [];
        $creados = 0;
        $repetidos = 0;

        foreach ($filas as $f) {
            $nombreCompleto = trim(implode(' ', array_filter([$f['nombres'] ?? null, $f['ap1'] ?? null, $f['ap2'] ?? null])));
            $nn = $this->norm($nombreCompleto);
            $tok = $this->tokens($nombreCompleto);
            $id = null;

            // documento
            if (!empty($f['dni']) && strlen(ltrim($f['dni'], '0')) >= 7 && isset($indiceDoc[ltrim($f['dni'], '0')])) {
                $id = $elegir($indiceDoc[ltrim($f['dni'], '0')], $f);
            }
            // nombre exacto (con preferencia por la misma fecha)
            if (!$id && isset($indiceNombre[$nn])) {
                $id = $elegir($indiceNombre[$nn], $f);
            }
            // nombre contenido y misma fecha
            if (!$id && !empty($f['fecha'])) {
                foreach ($clientes as $c) {
                    if (isset($porId[$c->id]) && substr((string) $c->created_at, 0, 10) === $f['fecha'] && $this->contenido($tok, $tokens[$c->id] ?? []) && !$this->esDifusion($c)) {
                        $id = $c->id;
                        break;
                    }
                }
            }
            // correo o celular
            if (!$id) {
                foreach (array_filter([$f['correo'] ?? null, $f['correo_empresa'] ?? null]) as $e) {
                    if (isset($indiceCorreo[mb_strtolower(trim($e))]) && count(array_unique($indiceCorreo[mb_strtolower(trim($e))])) === 1) {
                        $id = $indiceCorreo[mb_strtolower(trim($e))][0];
                        break;
                    }
                }
            }
            if (!$id && !empty($f['celular']) && isset($indiceTel[$f['celular']]) && count(array_unique($indiceTel[$f['celular']])) === 1) {
                $id = $indiceTel[$f['celular']][0];
            }
            // el nombre del sistema es la versión corta del del Excel, el mismo día
            if (!$id && !empty($f['fecha']) && count($tok) >= 2) {
                foreach ($clientes as $c) {
                    if (isset($porId[$c->id]) && substr((string) $c->created_at, 0, 10) === $f['fecha'] && !$this->esDifusion($c)
                        && $this->contenido($tokens[$c->id] ?? [], $tok)) {
                        $id = $c->id;
                        break;
                    }
                }
            }
            // el nombre del Excel es la versión corta de un cliente (único)
            if (!$id && count($tok) >= 2) {
                $cand = [];
                foreach ($clientes as $c) {
                    if (isset($porId[$c->id]) && !$this->esDifusion($c) && $this->contenido($tok, $tokens[$c->id] ?? [])) {
                        $cand[] = $c->id;
                    }
                }
                if (count($cand) === 1) {
                    $id = $cand[0];
                }
            }

            // ya unido a su empresa: el contacto figura en «contacto» del cliente-empresa o la fila trae el RUC de uno
            if (!$id) {
                foreach ($clientes as $c) {
                    if (($c->contacto && $this->norm($c->contacto) === $nn) || ($c->notas && stripos($c->notas, 'Contacto unido: ' . $nombreCompleto . '.') !== false)) {
                        $id = $c->id;
                        break;
                    }
                }
            }
            if (!$id && !empty($f['ruc']) && isset($indiceDoc[ltrim((string) $f['ruc'], '0')])) {
                $id = $indiceDoc[ltrim((string) $f['ruc'], '0')][0];
            }

            if (!$id) {
                $id = $this->crearDesdeFila($f, $nombreCompleto);
                $creados++;
                $this->reporte[] = "CREADO desde el Excel: fila {$f['fila']} «{$nombreCompleto}»";
                $porId[$id] = DB::table('clientes')->find($id);
                $tokens[$id] = $tok;
            }

            if (isset($usados[$id])) {
                $repetidos++;
                $this->reporte[] = "REPETIDO en el Excel: fila {$f['fila']} «{$nombreCompleto}» (ID " . ($f['id'] ?? 's/n') . ") es el mismo cliente que la fila {$usados[$id]}";
            } else {
                $usados[$id] = $f['fila'];
            }
            $asignaciones[$f['fila']] = $id;
        }

        return [$asignaciones, $creados, $repetidos];
    }

    private function crearDesdeFila(array $f, string $nombre): int
    {
        $segmento = match (Str::upper(rtrim((string) ($f['tipo'] ?? ''), '. '))) {
            'ARQ', 'ARQUITECTO' => 'ARQ', 'ING', 'INGENIERO' => 'ING', 'DIS', 'DISEÑADORA DE INTERIORES' => 'DIS', 'DEC' => 'DEC',
            'PN' => 'PN', 'PJ' => 'PJ', 'CF', 'CLIENTE FINAL' => 'CF', 'NEG', 'NEGOCIO' => 'NEG', default => null,
        };

        return DB::table('clientes')->insertGetId([
            'nombre' => mb_substr($nombre, 0, 255),
            'email' => $f['correo'] ?? null,
            'email_empresa' => $f['correo_empresa'] ?? null,
            'telefono' => !empty($f['celular']) ? mb_substr($f['celular'], 0, 20) : null,
            'segmento' => $segmento,
            'asesor' => $this->asesorLimpio($f['registrado_por'] ?? null),
            'estado' => 'activo',
            'created_at' => !empty($f['fecha']) ? $f['fecha'] . ' 00:00:00' : now(),
            'updated_at' => now(),
            'notas' => 'Agregado desde el Excel consolidado.',
        ]);
    }

    // ------------------------------------------------------------------ empresa + contacto = un solo cliente

    /**
     * Cada fila del Excel es un contacto con su empresa (RUC): van juntos en un solo cliente. Si el sistema tiene la empresa
     * como un cliente y a la persona como otro, se fusionan en el cliente-empresa (nombre y RUC de la empresa, la persona en
     * «contacto»), igual que el resto de empresas ya importadas.
     */
    private function unirEmpresas(array $filas, array &$asignaciones): int
    {
        $n = 0;
        foreach ($filas as $f) {
            $ruc = ltrim((string) ($f['ruc'] ?? ''), '0');
            if ($ruc === '' || strlen($ruc) < 11) {
                continue;
            }
            $persona = $asignaciones[$f['fila']] ?? null;
            $empresa = DB::table('clientes')->where('tipo_documento', 'RUC')->where('numero_documento', $ruc)->first();
            if (!$persona || !$empresa || $persona === $empresa->id) {
                continue;
            }
            $p = DB::table('clientes')->find($persona);
            if (!$p) {
                continue;
            }
            $nombrePersona = trim(implode(' ', array_filter([$f['nombres'] ?? null, $f['ap1'] ?? null, $f['ap2'] ?? null]))) ?: $p->nombre;
            $this->reporte[] = "UNIDA: «{$p->nombre}» + empresa «{$empresa->nombre}» ({$ruc}) = un solo cliente";

            // la persona aporta sus datos (teléfono, segmento, asesor…) y su documento personal pasa a «complementario»
            if ($p->tipo_documento === 'DNI' && $p->numero_documento && !$empresa->dni_complementario) {
                DB::table('clientes')->where('id', $empresa->id)->update(['dni_complementario' => $p->numero_documento]);
            }
            $this->fusionarDos($empresa->id, $p->id, true);
            $extra = [];
            if (blank($empresa->contacto)) {
                $extra['contacto'] = mb_substr($nombrePersona, 0, 120);
            }
            // se anota quién es cada contacto unido, para reconocerlo si se vuelve a ejecutar
            $notas = (string) DB::table('clientes')->where('id', $empresa->id)->value('notas');
            $marca = 'Contacto unido: ' . $nombrePersona . '.';
            if (!str_contains($notas, $marca)) {
                $extra['notas'] = trim($notas . "
" . $marca);
            }
            if ($extra) {
                DB::table('clientes')->where('id', $empresa->id)->update($extra);
            }
            foreach ($asignaciones as $fila => $cid) {
                if ($cid === $p->id) {
                    $asignaciones[$fila] = $empresa->id;
                }
            }
            $asignaciones[$f['fila']] = $empresa->id;
            $n++;
        }

        // cada contacto del Excel cuyo cliente es una empresa queda anotado, para reconocerlo en una nueva ejecución
        $empresas = DB::table('clientes')->where('tipo_documento', 'RUC')->whereIn('id', array_values($asignaciones))->get()->keyBy('id');
        foreach ($filas as $f) {
            $e = $empresas[$asignaciones[$f['fila']] ?? 0] ?? null;
            $nombre = trim(implode(' ', array_filter([$f['nombres'] ?? null, $f['ap1'] ?? null, $f['ap2'] ?? null])));
            if (!$e || $nombre === '' || $this->norm($nombre) === $this->norm($e->nombre)) {
                continue;
            }
            $marca = 'Contacto unido: ' . $nombre . '.';
            $notas = (string) DB::table('clientes')->where('id', $e->id)->value('notas');
            if (!str_contains($notas, $marca)) {
                DB::table('clientes')->where('id', $e->id)->update(['notas' => trim($notas . "
" . $marca)]);
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------ IDs

    /**
     * El ID existe solo para quien lo tiene en el Excel (KYR001…KYR912). Quien no lo tenía (filas de 2026, lista de difusión,
     * creados al importar) queda sin ID. Además se marca qué clientes pertenecen a la base comercial.
     */
    private function asignarCodigos(array $filas, array $asignaciones): void
    {
        $codigos = [];   // cliente_id => número
        $alternos = [];  // cliente_id => [números repetidos]
        foreach ($filas as $f) {
            $cid = $asignaciones[$f['fila']];
            $num = $f['id'] ?? null;
            if (!$num) {
                continue;
            }
            if (!isset($codigos[$cid])) {
                $codigos[$cid] = $num;
            } elseif ($num != $codigos[$cid]) {
                $alternos[$cid][] = $num;
            }
        }

        DB::table('clientes')->update(['codigo' => null]);
        foreach ($codigos as $cid => $num) {
            DB::table('clientes')->where('id', $cid)->update(['codigo' => 'KYR' . str_pad((string) $num, 3, '0', STR_PAD_LEFT)]);
        }
        foreach ($alternos as $cid => $nums) {
            $txt = 'En el Excel también figuraba como ' . implode(', ', array_map(fn ($n) => 'KYR' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), $nums)) . '.';
            $notas = (string) DB::table('clientes')->where('id', $cid)->value('notas');
            if (!str_contains($notas, $txt)) {
                DB::table('clientes')->where('id', $cid)->update(['notas' => trim($notas . "\n" . $txt)]);
            }
        }

        // Base comercial = solo quien tiene ID correlativo del Excel; el resto vive en la base completa
        DB::table('clientes')->whereNotNull('codigo')->update(['en_base_comercial' => true]);
        DB::table('clientes')->whereNull('codigo')->update(['en_base_comercial' => false]);
    }

    // ------------------------------------------------------------------ 3. seguimiento y proyectos

    private function importarSeguimientoYProyectos(array $filas, array $asignaciones): array
    {
        DB::table('cliente_seguimientos')->whereNotNull('fila_excel')->delete();
        DB::table('cliente_proyectos')->whereNotNull('fila_excel')->delete();
        $ahora = now();
        $nSeg = $nProy = 0;

        foreach ($filas as $f) {
            $cid = $asignaciones[$f['fila']];

            // datos propios del cliente
            $cl = [];
            if (!empty($f['ultima_atencion_por'])) {
                $cl['ultima_atencion_por'] = $this->asesorLimpio($f['ultima_atencion_por']);
            }
            if (!empty($f['ultima_atencion_en'])) {
                $cl['ultima_atencion_en'] = $f['ultima_atencion_en'];
            }
            if (!empty($f['clasificacion']) && !DB::table('clientes')->where('id', $cid)->value('clasificacion')) {
                $cl['clasificacion'] = Str::title(mb_strtolower($f['clasificacion']));
            }
            if ($cl) {
                DB::table('clientes')->where('id', $cid)->update($cl);
            }

            if (!empty($f['seguimiento']) || !empty($f['ultima_atencion_en'])) {
                $s = $f['seguimiento'] ?? [];
                $estado = $s['ultimo_estado'] ?? null;
                $nivel = $s['nivel'] ?? null;
                if ($nivel !== null && !ctype_digit((string) $nivel)) {
                    $estado = trim(($estado ? $estado . ' · ' : '') . $nivel);
                    $nivel = null;
                }
                DB::table('cliente_seguimientos')->insert([
                    'cliente_id' => $cid, 'fila_excel' => $f['fila'],
                    'fecha' => $f['ultima_atencion_en'] ?? null,
                    'atendido_por' => $this->asesorLimpio($f['ultima_atencion_por'] ?? null),
                    'tipo' => $this->tipoSeguimiento($s['tipo'] ?? null),
                    'situacion' => $this->situacion($s['situacion'] ?? null),
                    'busca' => isset($s['busca']) ? mb_substr($s['busca'], 0, 255) : null,
                    'ultimo_estado' => $estado,
                    'nivel' => $nivel !== null && (int) $nivel >= 1 && (int) $nivel <= 9 ? (int) $nivel : null,
                    'probabilidad' => $this->probabilidad($s['probabilidad'] ?? null),
                    'moneda' => $s['moneda'] ?? 'USD',
                    'monto_presupuestado' => $s['monto_presupuestado'] ?? null,
                    'monto_comprado' => $s['monto_comprado'] ?? null,
                    'resumen_visita' => $this->resumenVisita($s['resumen_visita'] ?? null),
                    'medio_contacto' => $this->medio($s['medio_contacto'] ?? null),
                    'observaciones' => $s['observaciones'] ?? null,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                $nSeg++;
            }

            if (!empty($f['proyecto'])) {
                $p = $f['proyecto'];
                $resultado = $this->resultado($p['resultado'] ?? null);
                $seg = $p['seguimiento'] ?? null;
                if (!empty($p['resultado']) && $resultado === null) {
                    $seg = trim(($seg ? $seg . ' · ' : '') . 'Resultado en el Excel: ' . $p['resultado']);
                }
                if (!empty($p['presupuesto_texto']) && preg_match('/[A-Za-z]{3,}/', preg_replace('/US\$|S\/\.?/i', '', $p['presupuesto_texto']))) {
                    $seg = trim(($seg ? $seg . ' · ' : '') . $p['presupuesto_texto']);
                }
                DB::table('cliente_proyectos')->insert([
                    'cliente_id' => $cid, 'fila_excel' => $f['fila'],
                    'codigo' => isset($p['codigo']) ? mb_substr($p['codigo'], 0, 40) : null,
                    'nombre' => isset($p['nombre']) ? mb_substr($p['nombre'], 0, 190) : null,
                    'persona_a_cargo' => isset($p['persona']) ? mb_substr(Str::title(mb_strtolower($p['persona'])), 0, 80) : null,
                    'prioridad' => $this->prioridad($p['prioridad'] ?? null),
                    'fecha_recepcion' => $p['recepcion'] ?? null,
                    'fecha_entrega_aprox' => $p['entrega_aprox'] ?? null,
                    'fecha_entrega_real' => $p['entrega_real'] ?? null,
                    'revisiones' => $p['revisiones'] ?? null,
                    'moneda' => $p['moneda'] ?? 'USD',
                    'monto_presupuestado' => $p['monto_presupuestado'] ?? null,
                    'monto_vendido' => $p['monto_vendido'] ?? null,
                    'resultado' => $resultado,
                    'seguimiento' => $seg,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                $nProy++;
            }
        }

        return [$nSeg, $nProy];
    }

    private function tipoSeguimiento(?string $t): ?string
    {
        return match ($this->norm($t)) {
            'puntual deco' => 'Puntual - Decorativa', 'puntual tec' => 'Puntual - Técnica', 'proyecto' => 'Proyecto', 'casacor' => 'Casacor',
            '' => null, default => Str::ucfirst(mb_strtolower((string) $t)),
        };
    }

    private function situacion(?string $s): ?string
    {
        return match ($this->norm($s)) {
            'si seguimiento' => 'En seguimiento', 'si perdido' => 'Perdido', 'en coordinacion con cliente' => 'En coordinación con el cliente',
            'licitacion por confirmar' => 'Licitación por confirmar', '' => null, default => Str::ucfirst(mb_strtolower((string) $s)),
        };
    }

    private function probabilidad($v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (!preg_match('/\d+(?:\.\d+)?/', (string) $v, $m)) {
            return null;
        }
        $n = (float) $m[0];

        return (int) round($n <= 1 ? $n * 100 : min($n, 100));
    }

    private function resumenVisita(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(preg_replace('/^\d+\s*[-.]\s*/', '', $v));
        if (ctype_digit($v)) {
            return match ((int) $v) { 1 => 'Venta', 4 => 'Pasaba', default => 'Opción ' . $v };
        }

        return Str::ucfirst(mb_strtolower($v));
    }

    /** Traduce el medio de contacto: «6», «2- CLIENTE ANTIGUO», «1-ELENA TESTINO», «VITRINA»… */
    private function medio(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d)(?:\.0)?$/', $v, $m)) {
            return self::MEDIOS[(int) $m[1]] ?? null;
        }
        $num = null;
        if (preg_match('/^(\d)\s*[-.]\s*(.+)$/', $v, $m)) {
            $num = (int) $m[1];
            $v = trim($m[2]);
        }
        $n = $this->norm($v);
        if (in_array($n, ['vitrina', 'showroom'], true)) {
            return 'Vitrina';
        }
        if (str_contains($n, 'cliente antiguo') || str_contains($n, 'cleinte antiguo')) {
            return 'Cliente antiguo';
        }
        if ($n === 'web') {
            return 'Web';
        }
        if (str_starts_with($n, 'red social')) {
            return Str::ucfirst(mb_strtolower($v));
        }
        if ($num && isset(self::MEDIOS[$num]) && $num !== 1) {
            return self::MEDIOS[$num];
        }

        return 'Referido por ' . Str::title(mb_strtolower($v));
    }

    private function prioridad(?string $p): ?string
    {
        return match (Str::upper(trim((string) $p))) { 'A', 'ALTA' => 'Alta', 'M', 'MEDIA' => 'Media', 'B', 'BAJA' => 'Baja', default => null };
    }

    private function resultado(?string $r): ?string
    {
        $r = Str::upper(trim((string) $r));
        $r = trim(preg_replace('/\s*=.*$/', '', $r));

        return match (true) {
            $r === '' => null,
            str_starts_with($r, 'ENV') => 'Enviado',
            str_starts_with($r, 'ENT') => 'Entregado',
            str_starts_with($r, 'EP'), str_starts_with($r, 'EN PROCESO') => 'En proceso',
            $r === 'G', str_starts_with($r, 'GANADO') => 'Ganado',
            $r === 'P', str_starts_with($r, 'PERDIDO'), str_starts_with($r, 'PÉRDIDO') => 'Perdido',
            default => null,
        };
    }

    // ------------------------------------------------------------------ 4. origen numérico

    private function limpiarOrigen(): int
    {
        $n = 0;
        foreach (DB::table('clientes')->whereRaw("origen REGEXP '^[0-9]+(\\\\.0)?$'")->get(['id', 'origen']) as $c) {
            $nombre = self::MEDIOS[(int) $c->origen] ?? null;
            DB::table('clientes')->where('id', $c->id)->update(['origen' => $nombre]);
            $n++;
        }
        // textos sueltos: «2- CLIENTE ANTIGUO», «CLEINTE ANTIGUO», «SR. ARTURO» (= referido por…)
        $canonicos = ['Vitrina', 'Cliente antiguo', 'Web', 'Referido', 'Otro', 'Red social'];
        foreach (DB::table('clientes')->whereNotNull('origen')->select('origen')->distinct()->pluck('origen') as $o) {
            if (in_array($o, $canonicos, true) || str_starts_with($o, 'Referido por ') || str_starts_with($o, 'Red social') || str_starts_with($o, 'Lista de difus')) {
                continue;
            }
            $nuevo = $this->medio($o);
            if ($nuevo !== $o) {
                $n += DB::table('clientes')->where('origen', $o)->update(['origen' => $nuevo]);
            }
        }

        return $n;
    }
}
