@extends('layouts.app', ['role' => 'COORDINADOR'])
@section('title', 'Portal de coordinación')
@section('nav')
<a href="#dashboard" data-section="dashboard" data-icon="home"><span>Inicio</span></a>
<p class="nav-group">Gestión académica</p>
<a href="#students" data-section="students" data-icon="users"><span>Estudiantes</span></a>
<a href="#accounts" data-section="accounts" data-icon="user"><span>Cuentas estudiantiles</span></a>
<a href="#curriculum" data-section="curriculum" data-icon="book"><span>Cursos y malla curricular</span></a>
<a href="#periods" data-section="periods" data-icon="calendar"><span>Períodos y ventanas</span></a>
<p class="nav-group">Prematrícula</p>
<a href="#enrollments" data-section="enrollments" data-icon="file"><span>Registros y constancias</span></a>
<a href="#demand" data-section="demand" data-icon="chart"><span>Demanda académica</span></a>
@endsection
