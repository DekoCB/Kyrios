<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Etapas que atraviesa una orden de compra; las edita el administrador
        if (!Schema::hasTable('etapas_seguimiento')) {
            Schema::create('etapas_seguimiento', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 80);
                $table->enum('tipo_compra', ['ambos', 'nacional', 'internacional'])->default('ambos');
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            $ahora = now();
            $base = [
                ['Orden emitida', 'ambos'],
                ['Confirmada por el proveedor', 'nacional'],
                ['Despachada por el proveedor', 'nacional'],
                ['En producción', 'internacional'],
                ['Embarcada', 'internacional'],
                ['En tránsito', 'internacional'],
                ['En aduana', 'internacional'],
                ['Llegada a Lima', 'internacional'],
                ['Recibida en almacén', 'ambos'],
            ];
            foreach ($base as $i => [$nombre, $tipo]) {
                DB::table('etapas_seguimiento')->insert(['nombre' => $nombre, 'tipo_compra' => $tipo, 'orden' => ($i + 1) * 10, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }

        // Copia de las etapas en cada orden: cambiar el catálogo no altera lo ya iniciado
        if (!Schema::hasTable('pedido_etapas')) {
            Schema::create('pedido_etapas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
                $table->foreignId('etapa_id')->nullable()->constrained('etapas_seguimiento')->nullOnDelete();
                $table->string('nombre', 80);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->date('fecha_prevista')->nullable();
                $table->date('fecha_real')->nullable();
                $table->string('notas', 255)->nullable();
                $table->foreignId('completada_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pedido_id', 'orden']);
            });
        }

        // Instalaciones y entregas programadas
        if (!Schema::hasTable('cronograma_eventos')) {
            Schema::create('cronograma_eventos', function (Blueprint $table) {
                $table->id();
                $table->enum('tipo', ['instalacion', 'entrega']);
                $table->string('titulo', 150);
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
                $table->string('direccion', 255)->nullable();
                $table->date('fecha');
                $table->time('hora')->nullable();
                $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('estado', ['programado', 'realizado', 'cancelado'])->default('programado');
                $table->text('notas')->nullable();
                $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['fecha', 'estado']);
                $table->index('responsable_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cronograma_eventos');
        Schema::dropIfExists('pedido_etapas');
        Schema::dropIfExists('etapas_seguimiento');
    }
};
