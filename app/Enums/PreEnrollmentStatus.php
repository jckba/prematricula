<?php

namespace App\Enums;

enum PreEnrollmentStatus: string
{
    case BORRADOR = 'BORRADOR';
    case ENVIADA = 'ENVIADA';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';
}
