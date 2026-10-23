<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'contacto')) {
                $table->string('contacto', 120)->nullable()->after('nombre')->comment('Persona de contacto (para empresas)');
            }
            if (!Schema::hasColumn('clientes', 'notas')) {
                $table->text('notas')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['contacto', 'notas']);
        });
    }
};
