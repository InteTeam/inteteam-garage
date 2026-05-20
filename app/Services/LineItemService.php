<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LineItem;
use Illuminate\Database\Eloquent\Collection;

final class LineItemService
{
    public function getAll(): Collection
    {
        return LineItem::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): LineItem
    {
        return LineItem::create($data);
    }

    public function update(LineItem $lineItem, array $data): LineItem
    {
        $lineItem->update($data);

        return $lineItem->fresh();
    }

    public function delete(LineItem $lineItem): void
    {
        $lineItem->delete();
    }
}
