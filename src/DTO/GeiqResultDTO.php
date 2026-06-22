<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Response;

class GeiqResultDTO
{
    public function __construct(
        private bool $success,
        private ?Response $response = null,
        private ?string $errorMessage = null,
        private ?int $recordCount = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getRecordCount(): ?int
    {
        return $this->recordCount;
    }

    public static function success(Response $response, int $recordCount): self
    {
        return new self(true, $response, null, $recordCount);
    }

    public static function error(string $errorMessage): self
    {
        return new self(false, null, $errorMessage, null);
    }
}
