<?php

use App\Support\SeccionesProforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones_ubicacion', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['piso', 'ambiente']);
            $table->string('nombre', 60);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'nombre']);
        });

        $ahora = now();
        foreach (['piso' => SeccionesProforma::PISOS, 'ambiente' => SeccionesProforma::AMBIENTES] as $tipo => $nombres) {
            foreach (array_values($nombres) as $i => $nombre) {
                DB::table('opciones_ubicacion')->insert(['tipo' => $tipo, 'nombre' => $nombre, 'orden' => $i + 1, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones_ubicacion');
    }
};
