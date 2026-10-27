<?php

declare(strict_types=1);

namespace EggChanger\Http\Controllers;

use EggChanger\Models\EggPorts;
use EggChanger\Models\Group;
use EggChanger\Panel\Panel;
use EggChanger\Support\Ports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;

final class ServerController
{
    public function __construct(private readonly Panel $panel) {}

    public function options(Request $request, Server $server): JsonResponse
    {
        $this->authorize($request, $server);

        $ranges = EggPorts::byEgg();
        $port = $this->panel->primaryPort($server);

        return new JsonResponse([
            'current' => ['id' => $server->egg->id, 'name' => $server->egg->name],
            'eggs' => $this->choices($server)->map(static fn (Egg $egg): array => [
                'id' => $egg->id,
                'name' => $egg->name,
                'description' => $egg->description,
                'port_range' => ! Ports::allows(Ports::ranges($ranges[$egg->id] ?? '') ?? [], $port) ? $ranges[$egg->id] : null,
            ])->values(),
            'can_reinstall' => (bool) $request->user()?->can('settings.reinstall', $server),
        ]);
    }

    public function change(Request $request, Server $server): JsonResponse
    {
        $this->authorize($request, $server);

        $data = $request->validate([
            'egg' => ['required', 'integer'],
            'keep_variables' => ['required', 'boolean'],
            'reinstall' => ['required', 'boolean'],
        ]);

        $egg = $this->choices($server)->firstWhere('id', (int) $data['egg']);
        abort_if($egg === null, 422, 'This server cannot switch to that egg.');
        abort_unless($this->panel->isStopped($server), 409, 'Stop the server before changing its egg.');
        abort_if($data['reinstall'] && ! $request->user()?->can('settings.reinstall', $server), 403, 'You do not have permission to reinstall this server.');

        $ranges = EggPorts::rangesFor($egg->id);
        $before = $this->panel->address($server);

        if (! Ports::allows($ranges, $this->panel->primaryPort($server))) {
            abort_if($this->panel->freeAllocationIn($server, $ranges) === null, 409, sprintf('No port in %s is free on this node. Ask an administrator.', Ports::format($ranges)));
        }

        $server = $this->panel->changeEgg($server, $egg, (bool) $data['keep_variables']);

        // Another extension may have moved the server during the egg change already.
        if (! Ports::allows($ranges, $this->panel->primaryPort($server))) {
            $allocation = $this->panel->freeAllocationIn($server, $ranges);
            $server = $allocation === null ? $server : $this->panel->movePrimary($server, $allocation);
        }

        if ($data['reinstall']) {
            $this->panel->reinstall($server);
        }

        $after = $this->panel->address($server);

        return new JsonResponse([
            'current' => ['id' => $egg->id, 'name' => $egg->name],
            'reinstalling' => (bool) $data['reinstall'],
            'address' => $after !== $before ? $after : null,
        ]);
    }

    /** @return Collection<int, Egg> */
    private function choices(Server $server): Collection
    {
        return Group::query()
            ->whereHas('eggs', static fn ($query) => $query->where('eggs.id', $server->egg_id))
            ->with('eggs')
            ->get()
            ->flatMap(static fn (Group $group) => $group->eggs)
            ->unique('id')
            ->reject(static fn (Egg $egg): bool => $egg->id === $server->egg_id)
            ->sortBy('name')
            ->values();
    }

    private function authorize(Request $request, Server $server): void
    {
        abort_unless((bool) $request->user()?->can('ext.egg-changer.change', $server), 403, 'You do not have permission to change the egg.');
    }
}
