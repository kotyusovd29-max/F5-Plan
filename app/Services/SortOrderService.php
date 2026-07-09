<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

class SortOrderService
{
    public function reorder(Builder|Relation $query, array $orderedIds): void
    {
        $existingIds = $query->pluck('id')->sort()->values();
        $orderedUnique = collect($orderedIds)->map(fn ($id) => (int) $id)->values();
        $orderedSorted = $orderedUnique->sort()->values();

        if ($existingIds->count() !== $orderedSorted->count() || $existingIds->diff($orderedSorted)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'Некорректный порядок элементов.',
            ]);
        }

        foreach ($orderedUnique as $index => $id) {
            (clone $query)->where('id', $id)->update(['sort_order' => $index + 1]);
        }
    }
}
