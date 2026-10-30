@extends('layouts.app', ['role' => 'COORDINADOR'])
@section('title', 'Portal de coordinación')
@section('nav')
<a href="#dashboard" data-section="dashboard" data-icon="home"><span>Inicio</span></a>
<p class="nav-group">Gestión académica</p>
<a href="#students" data-section="students" data-icon="users"><span>Estudiantes</span></a>
<a href="#accounts" data-section="accounts" data-icon="user"><span>Cuentas estudiantiles</span></a>
<a href="#curriculum" data-section="curriculum" data-icon="book"><span>Cursos y malla curricular</span></a>
<a href="#periods" data-section="periods" data-icon="calendar"><span>Períodos y ventanas</span></a>
<a href="#results" data-section="results" data-icon="check"><span>Resultados oficiales</span></a>
<p class="nav-group">Prematrícula</p>
<a href="#enrollments" data-section="enrollments" data-icon="file"><span>Registros y constancias</span></a>
<a href="#demand" data-section="demand" data-icon="chart"><span>Demanda académica</span></a>
<p class="nav-group">Planificación docente</p>
<a href="#teachers" data-section="teachers" data-icon="users"><span>Docentes</span></a>
<a href="#assignments" data-section="assignments" data-icon="layers"><span>Asignaciones</span></a>
<a href="#load" data-section="load" data-icon="chart"><span>Carga y cobertura</span></a>
@endsection
