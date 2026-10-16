<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_documents_every_versioned_route_and_has_no_unresolved_local_references(): void
    {
        $api = Yaml::parseFile(base_path('OpenAPI/openapi.yaml'));
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $uri = '/'.preg_replace('/\{([^}:]+):[^}]+\}/', '{$1}', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' '.$uri;
            }
        }
        $documented = [];
        $ids = [];
        foreach ($api['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                $documented[] = strtoupper($method).' '.$path;
                $ids[] = $operation['operationId'];
            }
        }
        sort($actual);
        sort($documented);

        $this->assertSame($actual, $documented);
        $this->assertCount(count(array_unique($ids)), $ids);
        $this->assertSame([], $api['paths']['/api/v1/login']['post']['security']);
        $this->checkReferences($api, $api);
    }

    public function test_postman_has_separate_role_tokens_and_required_resource_variables_without_secrets(): void
    {
        $collection = json_decode(file_get_contents(base_path('OpenAPI/Prematricula.postman_collection.json')), true, flags: JSON_THROW_ON_ERROR);
        $variables = array_column($collection['variable'], 'value', 'key');
        foreach (['base_url', 'student_token', 'director_token', 'secretary_token', 'academic_period_id',
            'course_id', 'student_id', 'pre_enrollment_id', 'teacher_id'] as $key) {
            $this->assertArrayHasKey($key, $variables);
        }
        foreach (['student_token', 'director_token', 'secretary_token'] as $key) {
            $this->assertSame('', $variables[$key]);
        }
        $this->assertSame('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $collection['info']['schema']);
    }

    #[TestWith(['GET', '/api', 404])]
    #[TestWith(['GET', '/api/v1/not-found', 404])]
    #[TestWith(['GET', '/api/v1/login', 405])]
    #[TestWith(['GET', '/api/v1/me', 401])]
    #[TestWith(['POST', '/api/v1/login', 422])]
    public function test_api_errors_use_json_even_when_the_client_requests_html(string $method, string $uri, int $status): void
    {
        $response = $this->call($method, $uri, server: ['HTTP_ACCEPT' => 'text/html']);

        $response->assertStatus($status)->assertHeader('Content-Type', 'application/json')->assertJsonStructure(['message']);
    }

    public function test_forbidden_and_rate_limited_responses_are_json(): void
    {
        Sanctum::actingAs(Student::factory()->create()->user);
        $this->get('/api/v1/coordinator/teachers', ['Accept' => 'text/html'])
            ->assertForbidden()->assertHeader('Content-Type', 'application/json');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/api/v1/login', ['identifier' => 'unknown', 'password' => 'incorrect'])->assertUnauthorized();
        }

        $this->post('/api/v1/login', ['identifier' => 'unknown', 'password' => 'incorrect'], ['Accept' => 'text/html'])
            ->assertTooManyRequests()->assertHeader('Content-Type', 'application/json')->assertHeader('Retry-After');
    }

    public function test_unhandled_api_exceptions_do_not_expose_debug_messages_or_traces(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        Route::get('api/v1/test-failure', fn () => throw new RuntimeException('PRIVATE_EXCEPTION_PAYLOAD'));

        $this->get('/api/v1/test-failure', ['Accept' => 'text/html'])->assertInternalServerError()
            ->assertHeader('Content-Type', 'application/json')->assertJsonStructure(['message'])
            ->assertDontSee('PRIVATE_EXCEPTION_PAYLOAD')->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');
    }

    public function test_cors_allows_configured_frontend_and_exposes_download_headers(): void
    {
        $this->options('/api/v1/login', [], ['Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'Authorization,Content-Type'])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $this->options('/api/v1/login', [], ['Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'POST'])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    /** @param array<string|int, mixed> $node
     * @param  array<string, mixed>  $api
     */
    private function checkReferences(array $node, array $api): void
    {
        foreach ($node as $key => $value) {
            if ($key === '$ref' && str_starts_with($value, '#/')) {
                $target = $api;
                foreach (explode('/', substr($value, 2)) as $part) {
                    $this->assertArrayHasKey($part, $target, $value);
                    $target = $target[$part];
                }
            } elseif (is_array($value)) {
                $this->checkReferences($value, $api);
            }
        }
    }
}
