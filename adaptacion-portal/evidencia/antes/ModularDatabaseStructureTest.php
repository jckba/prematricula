<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ModularDatabaseStructureTest extends TestCase
{
    private const EXPECTED_COUNTS = [
        'prematricula' => 3,
        'gestion_matriculas' => 9,
        'cotecu' => 10,
        'gestion_horarios' => 15,
        'gestion_practicas' => 11,
        'calidad' => 12,
        'tesis' => 22,
        'bienestar' => 18,
        'investigacion_formativa' => 11,
        'seguimiento_estudiante' => 23,
        'seguimiento_egresado' => 40,
    ];

    private const PUBLIC_SURFACE_COUNTS = [
        'web_institucional' => 13,
    ];

    private const SHARED_COUNTS = [
        'core' => 22,
        'seguridad' => 10,
        'comun' => 7,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }
    }

    public function test_enabled_modules_have_the_expected_table_counts(): void
    {
        foreach (self::EXPECTED_COUNTS as $schema => $expected) {
            $actual = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_type', 'BASE TABLE')
                ->count();

            $this->assertSame($expected, $actual, "Conteo incorrecto en {$schema}");
        }
    }

    public function test_shared_schemas_have_the_expected_table_counts(): void
    {
        foreach (self::SHARED_COUNTS as $schema => $expected) {
            $actual = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_type', 'BASE TABLE')
                ->count();

            $this->assertSame($expected, $actual, "Conteo incorrecto en {$schema}");
        }
    }

    public function test_public_surfaces_have_the_expected_table_counts(): void
    {
        foreach (self::PUBLIC_SURFACE_COUNTS as $schema => $expected) {
            $actual = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_type', 'BASE TABLE')
                ->count();

            $this->assertSame($expected, $actual, "Conteo incorrecto en {$schema}");
        }
    }

    public function test_module_catalog_lists_the_eleven_modules(): void
    {
        $this->assertSame(11, DB::table('seguridad.modulo_sistema')->count());
        $this->assertFalse(DB::table('seguridad.modulo_sistema')->where('codigo', 'web_institucional')->exists());
    }

    public function test_module_foreign_keys_never_target_another_module(): void
    {
        $moduleSchemas = array_keys(self::EXPECTED_COUNTS);
        $placeholders = implode(', ', array_fill(0, count($moduleSchemas), '?'));

        $invalid = DB::select(<<<SQL
            SELECT child_ns.nspname AS child_schema,
                   child.relname AS child_table,
                   constraint_definition.conname,
                   parent_ns.nspname AS parent_schema,
                   parent.relname AS parent_table
            FROM pg_constraint constraint_definition
            JOIN pg_class child ON child.oid = constraint_definition.conrelid
            JOIN pg_namespace child_ns ON child_ns.oid = child.relnamespace
            JOIN pg_class parent ON parent.oid = constraint_definition.confrelid
            JOIN pg_namespace parent_ns ON parent_ns.oid = parent.relnamespace
            WHERE constraint_definition.contype = 'f'
              AND child_ns.nspname IN ({$placeholders})
              AND parent_ns.nspname <> child_ns.nspname
              AND parent_ns.nspname NOT IN ('core', 'seguridad', 'comun', 'public')
            SQL, $moduleSchemas);

        $this->assertSame([], $invalid);
    }

    public function test_four_cross_schema_functions_exist(): void
    {
        $functions = collect(DB::select(<<<'SQL'
            SELECT n.nspname || '.' || p.proname AS function_name
            FROM pg_proc p
            JOIN pg_namespace n ON n.oid = p.pronamespace
            WHERE (n.nspname, p.proname) IN (
                ('gestion_matriculas', 'calcular_creditos_matriculados'),
                ('gestion_practicas', 'calcular_nota_final'),
                ('calidad', 'calcular_valor_indicador'),
                ('tesis', 'calcular_nota_sustentacion')
            )
            ORDER BY function_name
            SQL))->pluck('function_name')->all();

        $this->assertSame([
            'calidad.calcular_valor_indicador',
            'gestion_matriculas.calcular_creditos_matriculados',
            'gestion_practicas.calcular_nota_final',
            'tesis.calcular_nota_sustentacion',
        ], $functions);
    }
}
