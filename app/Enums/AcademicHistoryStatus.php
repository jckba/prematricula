<?php

namespace App\Enums;

enum AcademicHistoryStatus: string
{
    case EN_CURSO = 'EN_CURSO';
    case APROBADO = 'APROBADO';
    case DESAPROBADO = 'DESAPROBADO';
    case RETIRADO = 'RETIRADO';
}
