<?php

namespace App\Enums;

enum AcademicValidationStatus: string
{
    case PENDIENTE_RESULTADOS = 'PENDIENTE_RESULTADOS';
    case VALIDA = 'VALIDA';
    case REQUIERE_AJUSTE = 'REQUIERE_AJUSTE';
}
