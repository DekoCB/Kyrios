<?php

namespace Tests\Feature;

use App\Database\ReintentaMySqlConnector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

/** La saturación pasajera de MySQL en el hosting no debe verse como un error 500. */
class BaseOcupadaTest extends TestCase
{
    private function saturada(string $mensaje = 'SQLSTATE[08004] [1040] Too many connections'): QueryException
    {
        return new QueryException('mysql', 'select * from sessions', [], new PDOException($mensaje));
    }

    public function test_reconoce_los_fallos_pasajeros_de_conexion(): void
    {
        $this->assertTrue(ReintentaMySqlConnector::esPasajero($this->saturada()));
        $this->assertTrue(ReintentaMySqlConnector::esPasajero($this->saturada('SQLSTATE[HY000] [2002] Operation not permitted')));
        $this->assertFalse(ReintentaMySqlConnector::esPasajero($this->saturada('SQLSTATE[42S02]: Base table or view not found')));
        $this->assertFalse(ReintentaMySqlConnector::esPasajero(new \RuntimeException('otro error')));
    }

    public function test_una_base_saturada_responde_503_con_reintento_en_vez_de_500(): void
    {
        Route::get('/_prueba-saturada', fn () => throw $this->saturada());
        Route::get('/_prueba-error', fn () => throw $this->saturada('SQLSTATE[42S02]: Base table or view not found'));

        $this->get('/_prueba-saturada')->assertStatus(503)->assertHeader('Retry-After', '3')->assertSee('muy ocupado');
        $this->getJson('/_prueba-saturada')->assertStatus(503)->assertJsonPath('message', 'El sistema está muy ocupado. Intenta de nuevo en unos segundos.');
        $this->get('/_prueba-error')->assertStatus(500);
    }

    public function test_el_conector_de_mysql_es_el_que_reintenta(): void
    {
        $this->assertInstanceOf(ReintentaMySqlConnector::class, app('db.connector.mysql'));
    }
}
