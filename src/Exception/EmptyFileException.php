<?php

namespace App\Exception;

class EmptyFileException extends MoulinetteException
{
    public function __construct(string $filename, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Le fichier '{$filename}' est vide ou ne contient pas de données valides.";
        parent::__construct($message, $code, $previous);
    }
}