<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

/**
 * Class InvalidDependencyException
 * @package FluencePrototype\Dispatcher
 */
class InvalidDependencyException extends Exception
{

    /**
     * InvalidDependencyException constructor.
     * @param string $message
     * @param Throwable|null $previous
     */
    #[Pure] public function __construct(string $message = '', Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous);
    }

}