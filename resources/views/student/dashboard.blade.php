@extends('layouts.app', ['role' => 'ESTUDIANTE'])
@section('title', 'Portal del estudiante')
@section('nav')
<a href="#dashboard" data-section="dashboard" data-icon="home"><span>Inicio</span></a>
<a href="#pre-enrollment" data-section="pre-enrollment" data-icon="book"><span>Mi prematrícula</span></a>
<a href="#academic-history" data-section="academic-history" data-icon="history"><span>Historial académico</span></a>
<a href="#enrollments" data-section="enrollments" data-icon="file"><span>Solicitudes y constancias</span></a>
<a href="#catalog" data-section="catalog" data-icon="layers"><span>Catálogo de asignaturas</span></a>
@endsection
