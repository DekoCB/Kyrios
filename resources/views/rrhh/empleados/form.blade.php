@extends('layouts.modulo')
@php $e = $empleado; $esNuevo = !$e; @endphp
@section('titulo', $esNuevo ? 'Nuevo empleado' : 'Editar ' . $e->nombre_completo)
@section('subtitulo', 'Datos personales, laborales y de pago')

@php
    $v = fn ($campo, $defecto = '') => old($campo, $e?->{$campo} instanceof \DateTimeInterface ? $e->{$campo}->format('Y-m-d') : ($e?->{$campo} ?? $defecto));
    $hora = fn ($campo, $defecto) => old($campo, $e ? substr($e->{$campo}, 0, 5) : $defecto);
    $caja = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring focus:ring-blue-200';
@endphp

@section('contenido')
    <form method="POST" action="{{ $esNuevo ? route('rrhh.empleados.store') : route('rrhh.empleados.update', $e) }}" class="max-w-4xl space-y-6">
        @csrf
        @unless($esNuevo) @method('PUT') @endunless

        <section class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-4"><i class="fas fa-user text-gray-400 mr-1"></i> Datos personales</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Nombres *</label><input name="nombres" value="{{ $v('nombres') }}" required maxlength="120" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Apellidos *</label><input name="apellidos" value="{{ $v('apellidos') }}" required maxlength="120" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">DNI *</label><input name="dni" value="{{ $v('dni') }}" required maxlength="8" inputmode="numeric" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="{{ $v('fecha_nacimiento') }}" max="{{ today()->toDateString() }}" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Sexo</label>
                    <select name="sexo" class="{{ $caja }}"><option value="">—</option><option value="F" @selected($v('sexo') === 'F')>Femenino</option><option value="M" @selected($v('sexo') === 'M')>Masculino</option></select></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Estado civil</label>
                    <select name="estado_civil" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::ESTADOS_CIVILES as $k => $t)<option value="{{ $k }}" @selected($v('estado_civil') === $k)>{{ $t }}</option>@endforeach</select></div>
                <div class="md:col-span-3"><label class="block text-xs font-medium text-gray-600 mb-1">Dirección</label><input name="direccion" value="{{ $v('direccion') }}" maxlength="255" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Teléfono</label><input name="telefono" value="{{ $v('telefono') }}" maxlength="20" class="{{ $caja }}"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">Correo personal</label><input type="email" name="email_personal" value="{{ $v('email_personal') }}" maxlength="150" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Contacto de emergencia</label><input name="contacto_emergencia" value="{{ $v('contacto_emergencia') }}" maxlength="120" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Teléfono de emergencia</label><input name="telefono_emergencia" value="{{ $v('telefono_emergencia') }}" maxlength="20" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Parentesco</label><input name="parentesco_emergencia" value="{{ $v('parentesco_emergencia') }}" maxlength="40" class="{{ $caja }}"></div>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-4"><i class="fas fa-briefcase text-gray-400 mr-1"></i> Datos laborales</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Cargo *</label><input name="cargo" value="{{ $v('cargo') }}" required maxlength="120" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Área</label>
                    <select name="area" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::AREAS as $a)<option value="{{ $a }}" @selected($v('area') === $a)>{{ $a }}</option>@endforeach</select></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Usuario del sistema</label>
                    <select name="user_id" class="{{ $caja }}"><option value="">Sin usuario</option>@foreach($usuarios as $u)<option value="{{ $u->id }}" @selected((string) $v('user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->role?->nombre }})</option>@endforeach</select>
                    <p class="text-[11px] text-gray-400 mt-1">Con un usuario vinculado, la persona marca su propia asistencia.</p></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Tipo de contrato *</label>
                    <select name="tipo_contrato" class="{{ $caja }}">@foreach(\App\Models\Empleado::TIPOS_CONTRATO as $k => $t)<option value="{{ $k }}" @selected($v('tipo_contrato', 'indefinido') === $k)>{{ $t }}</option>@endforeach</select></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Fecha de ingreso *</label><input type="date" name="fecha_ingreso" value="{{ $v('fecha_ingreso') }}" required max="{{ today()->toDateString() }}" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Fin de contrato (plazo fijo)</label><input type="date" name="fecha_fin_contrato" value="{{ $v('fecha_fin_contrato') }}" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Sueldo básico (S/)</label><input type="number" step="0.01" min="0" name="sueldo_basico" value="{{ $v('sueldo_basico') }}" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Hora de entrada *</label><input type="time" name="hora_entrada" value="{{ $hora('hora_entrada', '08:30') }}" required class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Hora de salida *</label><input type="time" name="hora_salida" value="{{ $hora('hora_salida', '18:00') }}" required class="{{ $caja }}"></div>
                @if($esNuevo || $e->estado !== 'cesado')
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                        <select name="estado" class="{{ $caja }}"><option value="activo" @selected($v('estado', 'activo') === 'activo')>Activo</option><option value="licencia" @selected($v('estado') === 'licencia')>En licencia</option></select></div>
                @endif
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-4"><i class="fas fa-piggy-bank text-gray-400 mr-1"></i> Pensión y pago</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Sistema de pensiones</label>
                    <select name="sistema_pension" class="{{ $caja }}"><option value="">—</option><option value="AFP" @selected($v('sistema_pension') === 'AFP')>AFP</option><option value="ONP" @selected($v('sistema_pension') === 'ONP')>ONP</option></select></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">AFP</label><input name="afp_nombre" value="{{ $v('afp_nombre') }}" maxlength="40" placeholder="Integra, Prima, Profuturo, Habitat" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">CUSPP</label><input name="cuspp" value="{{ $v('cuspp') }}" maxlength="20" class="{{ $caja }}"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Banco</label><input name="banco" value="{{ $v('banco') }}" maxlength="40" class="{{ $caja }}"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">N.º de cuenta / CCI</label><input name="numero_cuenta" value="{{ $v('numero_cuenta') }}" maxlength="40" class="{{ $caja }}"></div>
                <div class="md:col-span-3"><label class="block text-xs font-medium text-gray-600 mb-1">Notas</label><textarea name="notas" rows="3" maxlength="2000" class="{{ $caja }}">{{ $v('notas') }}</textarea></div>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ $esNuevo ? route('rrhh.empleados.index') : route('rrhh.empleados.show', $e) }}" class="px-5 py-2 text-sm text-gray-600 bg-gray-200 hover:bg-gray-300 rounded-lg">Cancelar</a>
            <button class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"><i class="fas fa-save mr-1"></i>Guardar</button>
        </div>
    </form>
@endsection
