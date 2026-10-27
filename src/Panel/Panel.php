<?php

declare(strict_types=1);

namespace EggChanger\Panel;

use Pterodactyl\Contracts\Servers\ChangesServerEgg;
use Pterodactyl\Contracts\Servers\ReinstallsServers;
use Pterodactyl\Contracts\Servers\UpdatesServerBuild;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Facades\Daemon;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;

/**
 * The only place this extension touches Panel internals.
 */
final readonly class Panel
{
    public function __construct(private ChangesServerEgg $eggs, private ReinstallsServers $reinstalls, private UpdatesServerBuild $builder) {}

    public function isStopped(Server $server): bool
    {
        try {
            return in_array(Daemon::server($server)->details()['state'] ?? 'offline', ['offline', 'stopped'], true);
        } catch (DaemonConnectionException) {
            return false;
        }
    }

    public function changeEgg(Server $server, Egg $egg, bool $keepVariables): Server
    {
        $previous = $server->egg->name;
        $server = $this->eggs->change($server, $egg, $keepVariables);

        Activity::event('server:startup.edit')->subject($server)->property(['variable' => 'Egg', 'old' => $previous, 'new' => $egg->name])->log();

        return $server;
    }

    public function reinstall(Server $server): void
    {
        $this->reinstalls->reinstall($server);
        Activity::event('server:reinstall')->subject($server)->log();
    }

    /** Read from the database, as another extension may have moved the server since it was loaded. */
    public function primaryPort(Server $server): int
    {
        return (int) Allocation::query()->whereKey($server->allocation_id)->value('port');
    }

    public function address(Server $server): string
    {
        return Allocation::query()->findOrFail($server->allocation_id)->toString();
    }

    /**
     * A free allocation within the ranges on the server's node, on the IP of its primary allocation when one is free there.
     *
     * @param  list<array{int, int}>  $ranges
     */
    public function freeAllocationIn(Server $server, array $ranges): ?Allocation
    {
        return Allocation::query()->unassigned()->onNodes([$server->node_id])->onPorts([], $ranges)
            ->orderByRaw('ip = ? desc', [Allocation::query()->whereKey($server->allocation_id)->value('ip')])
            ->orderBy('port')
            ->first();
    }

    /** Makes the allocation the server's primary one and frees the old primary. */
    public function movePrimary(Server $server, Allocation $allocation): Server
    {
        $server = $this->builder->update($server, [
            'add_allocations' => [$allocation->id],
            'remove_allocations' => [$server->allocation_id],
            'database_limit' => $server->database_limit,
            'allocation_limit' => $server->allocation_limit,
            'backup_limit' => $server->backup_limit,
        ]);

        Activity::event('server:allocation.primary')->subject($server, $allocation)->property(['allocation' => $allocation->toString(), 'via' => 'egg-changer'])->log();

        return $server;
    }
}
