<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correo_institucional' => $this->correo_institucional,
            'rol' => $this->rol->value,
            'estado' => $this->estado->value,
            'cargo' => $this->cargo?->value,
        ];
    }
}
