<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INICIALES = [
        'clasificacion'        => ['GOLD', 'VIP', 'FRECUENTE', 'OCASIONAL', 'NUEVO', 'RECUPERADO'],
        'como_ingreso'         => ['Redes Sociales', 'Referido', 'Página Web', 'Evento', 'Publicidad digital', 'Auspicio', 'Otros'],
        'preferencia_contacto' => ['Instagram', 'Facebook', 'Whatsapp', 'Email', 'Llamada Telf.'],
        'especialidad'         => ['Lumínico/a', 'De interiores', 'Paisajista', 'Urbanista', 'Vivienda', 'Inmobiliario', 'Retail', 'Hospital', 'Hoteles', 'Oficinas'],
        'tipo_empresa'         => ['Constructora', 'Inmobiliaria', 'Estudio', 'Del Estado', 'E.I.R.L'],
    ];

    public function up(): void
    {
        // Listas de opciones que el administrador puede editar (agregar, renombrar, desactivar, eliminar)
        Schema::create('opciones_cliente', function (Blueprint $table) {
            $table->id();
            $table->string('grupo', 30);
            $table->string('nombre', 80);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['grupo', 'nombre']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('clasificacion', 80)->nullable()->after('segmento')->index();
            $table->string('como_ingreso', 80)->nullable()->after('clasificacion')->index();
            $table->string('preferencia_contacto', 80)->nullable()->after('como_ingreso')->index();
            $table->string('tipo_empresa', 80)->nullable()->after('empresa')->index();
        });

        $ahora = now();
        foreach (self::INICIALES as $grupo => $nombres) {
            foreach ($nombres as $i => $nombre) {
                DB::table('opciones_cliente')->insert([
                    'grupo' => $grupo, 'nombre' => $nombre, 'orden' => $i + 1, 'activo' => true,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }

        // «Origen» ya existía como texto libre: lo que coincide con una opción de «Cómo ingresó» se copia
        $equivalencias = [
            'Redes Sociales' => ['red', 'instagram', 'facebook', 'tiktok'],
            'Página Web'     => ['web', 'página', 'pagina'],
            'Referido'       => ['referid', 'recomend'],
            'Evento'         => ['evento', 'feria'],
        ];
        foreach ($equivalencias as $opcion => $claves) {
            DB::table('clientes')->whereNull('como_ingreso')->whereNotNull('origen')
                ->where(function ($q) use ($claves) {
                    foreach ($claves as $clave) {
                        $q->orWhere('origen', 'like', '%' . $clave . '%');
                    }
                })->update(['como_ingreso' => $opcion]);
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['clasificacion', 'como_ingreso', 'preferencia_contacto', 'tipo_empresa']);
        });
        Schema::dropIfExists('opciones_cliente');
    }
};
