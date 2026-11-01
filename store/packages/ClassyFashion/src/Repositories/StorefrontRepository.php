<?php

namespace ClassyFashion\Repositories;

use Webkul\Core\Repositories\CoreConfigRepository;

/**
 * Publish a complete homepage update atomically.
 */
class StorefrontRepository
{
    /**
     * Use Bagisto's canonical writer so its configuration cache is invalidated.
     */
    public function __construct(protected CoreConfigRepository $configs) {}

    /**
     * Preserve the previous homepage if any configuration write fails.
     */
    public function publish(array $data): void
    {
        $this->configs->getModel()->getConnection()->transaction(fn () => $this->configs->create($data));
    }
}
