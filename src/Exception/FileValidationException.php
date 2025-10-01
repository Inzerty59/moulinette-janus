<?php

namespace App\Exception;

class FileValidationException extends MoulinetteException
{
    private array $errors;

    public function __construct(array $errors, string $message = "Erreurs de validation des fichiers", int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsAsString(): string
    {
        return implode('<br>', $this->errors);
    }
}