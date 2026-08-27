<?php

namespace Tests\Feature;

use App\Models\FlujoCaja;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FlujoCajaReporteExcelTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureFlujoCajaColumns();
    }

    public function test_auditor_puede_descargar_excel_del_reporte(): void
    {
        $auditor = User::create([
            'name' => 'Auditor',
            'email' => 'auditor-reporte@test.local',
            'password' => 'password123',
            'role' => User::ROLE_AUDITOR,
        ]);

        FlujoCaja::query()->create([
            'fecha' => '2026-08-27',
            'tipo' => 'egreso',
            'categoria_egreso' => 'egreso_realizado',
            'banco' => 'Banesco',
            'titular' => 'Grupo JRZ',
            'motivo' => 'Pago proveedor',
            'tipo_gasto' => 'Compras',
            'monto_usd' => 10,
            'monto_bs' => 1000,
            'oculto' => false,
        ]);

        $response = $this->actingAs($auditor)->get(route('finanzas.flujo_caja.reporte', [
            'desde' => '2026-08-27',
            'hasta' => '2026-08-27',
            'cats' => 'egreso_realizado,otros_egresos,traslados,egreso_divisas',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type')
        );
        $this->assertSame('PK', substr($response->getContent(), 0, 2));
    }

    private function ensureFlujoCajaColumns(): void
    {
        $columns = [
            'categoria_egreso' => fn (Blueprint $table) => $table->string('categoria_egreso')->nullable(),
            'banco' => fn (Blueprint $table) => $table->string('banco')->nullable(),
            'titular' => fn (Blueprint $table) => $table->string('titular')->nullable(),
            'banco_receptor' => fn (Blueprint $table) => $table->string('banco_receptor')->nullable(),
            'titular_receptor' => fn (Blueprint $table) => $table->string('titular_receptor')->nullable(),
            'motivo' => fn (Blueprint $table) => $table->text('motivo')->nullable(),
            'diferencial_cambiario' => fn (Blueprint $table) => $table->decimal('diferencial_cambiario', 14, 2)->nullable(),
            'comision' => fn (Blueprint $table) => $table->decimal('comision', 14, 2)->nullable(),
            'oculto' => fn (Blueprint $table) => $table->boolean('oculto')->default(false),
        ];

        foreach ($columns as $name => $define) {
            if (! Schema::hasColumn('flujo_cajas', $name)) {
                Schema::table('flujo_cajas', $define);
            }
        }
    }
}
