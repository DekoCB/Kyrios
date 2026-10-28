<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las credenciales de SUNAT (clave SOL, token, contraseña del certificado, secreto de guías) se guardaban en
 * texto plano: un respaldo de la base de datos las exponía. Ahora se cifran con la clave de la aplicación.
 * Se amplían las columnas porque un valor cifrado ocupa mucho más que el texto original.
 */
return new class extends Migration
{
    private const COLUMNAS = ['sunat_clave_sol', 'sunat_token', 'api_key', 'certificado_pfx_password', 'gre_client_secret'];

    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->text($columna)->nullable()->change();
            }
        });

        foreach (DB::table('empresas')->get() as $fila) {
            $cambios = [];
            foreach (self::COLUMNAS as $columna) {
                $valor = $fila->{$columna} ?? null;
                if ($valor === null || $valor === '') {
                    continue;
                }
                try {
                    Crypt::decryptString($valor); // ya está cifrado
                } catch (DecryptException) {
                    $cambios[$columna] = Crypt::encryptString($valor);
                }
            }
            if ($cambios) {
                DB::table('empresas')->where('id', $fila->id)->update($cambios);
            }
        }
    }

    public function down(): void
    {
        // Se descifran los valores para que vuelvan a caber en las columnas originales.
        foreach (DB::table('empresas')->get() as $fila) {
            $cambios = [];
            foreach (self::COLUMNAS as $columna) {
                $valor = $fila->{$columna} ?? null;
                if ($valor === null || $valor === '') {
                    continue;
                }
                try {
                    $cambios[$columna] = Crypt::decryptString($valor);
                } catch (DecryptException) {
                    // ya estaba en texto plano
                }
            }
            if ($cambios) {
                DB::table('empresas')->where('id', $fila->id)->update($cambios);
            }
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->string('sunat_clave_sol', 100)->nullable()->change();
            $table->string('sunat_token', 500)->nullable()->change();
            $table->string('api_key', 300)->nullable()->change();
            $table->string('certificado_pfx_password', 200)->nullable()->change();
            $table->string('gre_client_secret', 200)->nullable()->change();
        });
    }
};
