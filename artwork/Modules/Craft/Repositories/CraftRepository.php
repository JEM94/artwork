<?php

namespace Artwork\Modules\Craft\Repositories;

use Artwork\Core\Database\Repository\BaseRepository;
use Artwork\Modules\Craft\Models\Craft;
use Illuminate\Database\Eloquent\Collection;

class CraftRepository extends BaseRepository
{
    /**
     * @return array<int, mixed>
     */
    public function syncUsers(Craft $craft, array $userIds): array
    {
        return $craft->users()->withTimestamps()->sync($userIds);
    }

    public function detachUsers(Craft $craft): int
    {
        return $craft->users()->detach();
    }

    public function getAll(array $with = [])
    {
        return Craft::query()->with($with)->get();
        /*$crafts = new Collection();
        // use here chunk method to avoid memory issues
        Craft::query()->with($with)->chunk(100, function ($craftsChunk) use (&$crafts): void {
            $crafts = $crafts->merge($craftsChunk);
        });

        return $crafts;*/
    }

    public function getAssignableByAllCrafts(): Collection
    {
        return Craft::query()->isAssignableByAll()->get();
    }

    public function findById(int $id): Craft
    {
        return Craft::find($id);
    }
}
