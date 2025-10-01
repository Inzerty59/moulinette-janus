<?php

namespace App\Exception;

class FileProcessingException extends MoulinetteException
{
    public function __construct(string $filename, string $operation, ?\Throwable $previous = null)
    {
        $message = "Erreur lors de {$operation} du fichier '{$filename}'";
        if ($previous) {
            $message .= ": " . $previous->getMessage();
        }
        parent::__construct($message, 0, $previous);
    }
}