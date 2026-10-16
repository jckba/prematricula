<?php

namespace Tests\Feature\UseCases\PreEnrollment;

use App\Models\PreEnrollmentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReviewPreEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['approve'])]
    #[TestWith(['reject'])]
    public function test_removed_review_routes_return_404_without_changing_submitted_requests(string $action): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $request = PreEnrollmentRequest::factory()->create(['estado' => 'ENVIADA', 'fecha_envio' => now()]);
        $this->withToken($coordinator->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/coordinator/pre-enrollments/'.$request->id.'/'.$action, ['comentario' => 'No debe aplicarse'])
            ->assertNotFound();

        $request->refresh();
        $this->assertSame('ENVIADA', $request->estado->value);
        $this->assertNull($request->reviewed_by);
        $this->assertNull($request->fecha_revision);
    }

    #[TestWith(['APROBADA'])]
    #[TestWith(['RECHAZADA'])]
    public function test_legacy_reviewed_requests_remain_readable_and_unchanged(string $state): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $request = PreEnrollmentRequest::factory()->create(['estado' => $state, 'reviewed_by' => $coordinator->id,
            'fecha_revision' => now(), 'comentarios_coordinador' => 'Comentario histórico']);
        $this->withToken($coordinator->createToken('test')->plainTextToken);

        $this->getJson('/api/v1/coordinator/pre-enrollments/'.$request->id)->assertOk()
            ->assertJsonPath('data.estado', $state)->assertJsonPath('data.comentarios_coordinador', 'Comentario histórico');

        $this->assertSame($state, $request->fresh()->estado->value);
        $this->assertSame($coordinator->id, $request->fresh()->reviewed_by);
    }

    public function test_draft_request_cannot_be_approved_through_the_removed_route(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $request = PreEnrollmentRequest::factory()->create();
        $this->withToken($coordinator->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/coordinator/pre-enrollments/'.$request->id.'/approve')->assertNotFound();

        $this->assertSame('BORRADOR', $request->fresh()->estado->value);
    }
}
