<?php

namespace Tests\Unit;

use App\Support\LaptopCaracteristicas;
use PHPUnit\Framework\TestCase;

class LaptopCaracteristicasTest extends TestCase
{
    private const SUBCAT = 'LAPTOS/COMPUTADORAS';

    public function test_laptop_gamer_con_todos_los_datos(): void
    {
        $c = LaptopCaracteristicas::extraer(
            'HP VICTUS 15.6 INCH FHD 144HZ IPS WINDOWS LAPTOP INTEL CORE I5-13420H 16GB RAM 512GB SSD RTX 4050 15-FA2701WM',
            self::SUBCAT
        );

        $this->assertSame([
            'Procesador' => 'Intel Core i5-13420H',
            'RAM' => '16 GB',
            'Almacenamiento' => '512 GB SSD',
            'Pantalla' => '15.6" · Full HD · 144 Hz',
            'Gráficos' => 'NVIDIA RTX 4050',
        ], $c);
    }

    public function test_formato_ram_barra_almacenamiento(): void
    {
        $c = LaptopCaracteristicas::extraer('APPLE LAPTOP MACBOOK AIR 2026 13" CON CHIP M5 16/512GB', self::SUBCAT);
        $this->assertSame('Apple M5', $c['Procesador']);
        $this->assertSame('16 GB', $c['RAM']);
        $this->assertSame('512 GB', $c['Almacenamiento']);
        $this->assertSame('13"', $c['Pantalla']);

        $c = LaptopCaracteristicas::extraer('LAPTOP DELL - INSPIRON 15.6" TOUCHSCREEN RYZEN 7 7730U 16/1TB', self::SUBCAT);
        $this->assertSame('AMD Ryzen 7 7730U', $c['Procesador']);
        $this->assertSame('1 TB', $c['Almacenamiento']);
        $this->assertSame('15.6" · táctil', $c['Pantalla']);

        $c = LaptopCaracteristicas::extraer('LENOVO IDEAPAD N4020 4+128GB', self::SUBCAT);
        $this->assertSame('Intel N4020', $c['Procesador']);
        $this->assertSame('4 GB', $c['RAM']);
        $this->assertSame('128 GB', $c['Almacenamiento']);
    }

    public function test_no_confunde_capacidad_con_modelo_de_procesador(): void
    {
        $c = LaptopCaracteristicas::extraer('LAPTOP HP OMNIBOOK 3 16" 2K TOUCHSCREEN INTEL CORE ULTRA 5 512GB SSD', self::SUBCAT);

        $this->assertSame('Intel Core Ultra 5', $c['Procesador']);
        $this->assertSame('512 GB SSD', $c['Almacenamiento']);
    }

    public function test_accesorios_no_tienen_caracteristicas(): void
    {
        foreach ([
            'BOLSO PARA LAPTOP Y TABLET 12/13 PULG',
            'MONITOR CURVO SAMSUNG 24" CF390',
            'DISCO DURO SOLIDO 256GB CEAMERE',
            'FUNDA RIGIDA DE PLASTICO PARA MACBOOK PRO DE 13"',
        ] as $nombre) {
            $this->assertSame([], LaptopCaracteristicas::extraer($nombre, self::SUBCAT), $nombre);
        }
    }

    public function test_productos_que_no_son_equipos(): void
    {
        $this->assertSame([], LaptopCaracteristicas::extraer('SAMSUNG GALAXY A15 6/128GB', 'TELEFONOS'));
    }
}
