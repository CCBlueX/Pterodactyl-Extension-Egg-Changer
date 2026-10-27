<?php

declare(strict_types=1);

namespace EggChanger\Models;

use EggChanger\Support\Ports;
use Illuminate\Database\Eloquent\Model;

/**
 * The ports a server gets when it switches to this egg.
 *
 * @property int $egg_id
 * @property string $port_range
 */
final class EggPorts extends Model
{
    public $incrementing = false;

    protected $table = 'ext_egg_changer_egg_ports';

    protected $primaryKey = 'egg_id';

    protected $fillable = ['egg_id', 'port_range'];

    /** @return array<int, string> Port range by egg ID. */
    public static function byEgg(): array
    {
        return self::query()->pluck('port_range', 'egg_id')->all();
    }

    /** @return list<array{int, int}> */
    public static function rangesFor(int $egg): array
    {
        return Ports::ranges((string) self::query()->whereKey($egg)->value('port_range')) ?? [];
    }
}
