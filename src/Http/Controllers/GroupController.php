<?php

declare(strict_types=1);

namespace EggChanger\Http\Controllers;

use Closure;
use EggChanger\Models\EggPorts;
use EggChanger\Models\Group;
use EggChanger\Support\Ports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Egg;

final class GroupController
{
    public function index(): JsonResponse
    {
        $ranges = EggPorts::byEgg();

        return new JsonResponse([
            'groups' => Group::query()->with('eggs:id,name')->orderBy('name')->get()->map(fn (Group $group): array => $this->present($group, $ranges)),
            'eggs' => Egg::query()->orderBy('name')->get(['id', 'name'])->map(static fn (Egg $egg): array => ['id' => $egg->id, 'name' => $egg->name, 'port_range' => $ranges[$egg->id] ?? null]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['group' => $this->save(new Group(), $request)], 201);
    }

    public function update(Request $request, int $group): JsonResponse
    {
        return new JsonResponse(['group' => $this->save(Group::query()->findOrFail($group), $request)]);
    }

    public function destroy(int $group): Response
    {
        Group::query()->findOrFail($group)->delete();

        return new Response('', 204);
    }

    /** @return array<string, mixed> */
    private function save(Group $group, Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'eggs' => ['required', 'array', 'min:2', 'max:200'],
            'eggs.*' => ['integer', 'distinct', 'exists:eggs,id'],
            'port_ranges' => ['nullable', 'array'],
            'port_ranges.*' => ['nullable', 'string', 'max:191', static function (string $attribute, mixed $value, Closure $fail): void {
                if (Ports::ranges((string) $value) === null) {
                    $fail('Enter ports and ranges like 5520-5600, 27015.');
                }
            }],
        ]);

        DB::transaction(static function () use ($group, $data): void {
            $group->fill(['name' => $data['name'], 'description' => $data['description'] ?? null])->save();
            $group->eggs()->sync($data['eggs']);

            foreach (isset($data['port_ranges']) ? $data['eggs'] : [] as $egg) {
                $range = Ports::format(Ports::ranges((string) ($data['port_ranges'][$egg] ?? '')) ?? []);

                $range === ''
                    ? EggPorts::query()->whereKey($egg)->delete()
                    : EggPorts::query()->updateOrCreate(['egg_id' => $egg], ['port_range' => $range]);
            }
        });

        return $this->present($group->load('eggs:id,name'), EggPorts::byEgg());
    }

    /**
     * @param  array<int, string>  $ranges
     * @return array<string, mixed>
     */
    private function present(Group $group, array $ranges): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'eggs' => $group->eggs->map(static fn (Egg $egg): array => ['id' => $egg->id, 'name' => $egg->name, 'port_range' => $ranges[$egg->id] ?? null])->values(),
        ];
    }
}
