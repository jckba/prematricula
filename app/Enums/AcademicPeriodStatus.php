<?php

namespace App\Enums;

enum AcademicPeriodStatus: string
{
    case EN_CURSO = 'EN_CURSO';
    case PLANIFICACION = 'PLANIFICACION';
    case PREMATRICULA = 'PREMATRICULA';
    case CERRADO = 'CERRADO';
}
