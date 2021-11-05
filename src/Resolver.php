<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Resolver
{

    /**
     * @var iResolver|mixed
     */
    private iResolver $resolver;

    /**
     * Resolver constructor.
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->resolver = new $className;
    }

    /**
     * @return iResolver
     */
    public function getResolver(): iResolver
    {
        return $this->resolver;
    }

}