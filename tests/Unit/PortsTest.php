<?php

declare(strict_types=1);

namespace EggChanger\Tests\Unit;

use EggChanger\Support\Ports;
use PHPUnit\Framework\TestCase;

final class PortsTest extends TestCase
{
    public function test_ranges_parse_ports_and_ranges(): void
    {
        $this->assertSame([[5520, 5600], [27015, 27015]], Ports::ranges(' 5520 - 5600 ,27015, '));
        $this->assertSame([], Ports::ranges(''));
        $this->assertSame('5520-5600, 27015', Ports::format([[5520, 5600], [27015, 27015]]));
    }

    public function test_ranges_reject_anything_but_ports(): void
    {
        foreach (['5600-5520', '0', '65536', '5520-70000', 'hytale', '5520:5600', '-5520'] as $value) {
            $this->assertNull(Ports::ranges($value), $value);
        }
    }

    public function test_allows_checks_bounds_and_empty_ranges(): void
    {
        $ranges = [[5520, 5600], [27015, 27015]];

        $this->assertTrue(Ports::allows($ranges, 5520));
        $this->assertTrue(Ports::allows($ranges, 5600));
        $this->assertTrue(Ports::allows($ranges, 27015));
        $this->assertFalse(Ports::allows($ranges, 5601));
        $this->assertFalse(Ports::allows($ranges, 25565));
        $this->assertTrue(Ports::allows([], 25565));
    }
}
