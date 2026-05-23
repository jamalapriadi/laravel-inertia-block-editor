<?php

namespace Jamalapr\InertiaBlockEditor\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Jamalapr\InertiaBlockEditor\Models\ContentBlock;

trait HasBlocks
{
    public function blocks(): MorphMany
    {
        return $this->morphMany(ContentBlock::class, 'blockable')->orderBy('sort_order');
    }
}
