<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Services\ModuleService;
use Illuminate\Support\Facades\DB;

final class PreMatriculaService extends ModuleService
{
    /**
     * @param  array<string, mixed>  $solicitud
     * @param  array<int, array<string, mixed>>  $detalles
     */
    public function crearSolicitud(array $solicitud, array $detalles): SolicitudPrematricula
    {
        return DB::transaction(function () use ($solicitud, $detalles): SolicitudPrematricula {
            $registro = SolicitudPrematricula::query()->create($solicitud);
            $registro->detalles()->createMany($detalles);

            return $registro->load('detalles');
        });
    }
}
