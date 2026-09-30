<?php

namespace App\Enums;

enum AcademicPeriodStatus: string
{
    case PLANIFICACION = 'PLANIFICACION';
    case PREMATRICULA = 'PREMATRICULA';
    case CERRADO = 'CERRADO';
}
