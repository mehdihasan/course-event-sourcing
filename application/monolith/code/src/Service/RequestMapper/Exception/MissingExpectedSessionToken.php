<?php

declare(strict_types=1);

namespace Galeas\Api\Service\RequestMapper\Exception;

use Galeas\Api\Common\ExceptionBase\UnauthorizedException;

class MissingExpectedSessionToken extends UnauthorizedException
{
    public static function getErrorIdentifier(): string
    {
        return 'Service_RequestMapper_MissingExpectedSessionToken';
    }
}
