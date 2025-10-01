<?php

namespace App\Exception;

class AgencyNotFoundException extends MoulinetteException
{
    public function __construct(int $agencyId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "L'agence avec l'ID {$agencyId} n'a pas été trouvée ou n'a pas de rubriques associées.";
        parent::__construct($message, $code, $previous);
    }
}