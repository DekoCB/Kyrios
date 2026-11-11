<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El cronograma se vincula con la venta que originó la instalación o entrega
        Schema::table('cronograma_eventos', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->after('pedido_id')->constrained('ventas')->nullOnDelete();
        });

        // Inventario de herramientas para instalaciones
        Schema::create('herramientas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);
            $table->string('categoria', 80)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ubicacion', 120)->nullable();
            $table->unsignedInteger('cantidad_total')->default(1);
            $table->unsignedInteger('cantidad_danada')->default(0);
            $table->string('estado', 10)->default('activa'); // activa | baja
            $table->timestamps();
        });

        Schema::create('herramienta_salidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('herramienta_id')->constrained('herramientas')->cascadeOnDelete();
            $table->foreignId('tecnico_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->foreignId('cronograma_evento_id')->nullable()->constrained('cronograma_eventos')->nullOnDelete();
            $table->string('destino', 200)->nullable();
            $table->dateTime('fecha_salida');
            $table->date('fecha_devolucion_esperada')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();

            // Retorno
            $table->string('estado', 10)->default('en_uso'); // en_uso | devuelta
            $table->dateTime('fecha_retorno')->nullable();
            $table->unsignedInteger('cantidad_buena')->default(0);
            $table->unsignedInteger('cantidad_danada')->default(0);
            $table->unsignedInteger('cantidad_perdida')->default(0);
            $table->text('notas_retorno')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['herramienta_id', 'estado']);
            $table->index(['tecnico_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herramienta_salidas');
        Schema::dropIfExists('herramientas');
        Schema::table('cronograma_eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venta_id');
        });
    }
};
