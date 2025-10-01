<?php

namespace App\DTO;

class MoulinetteResultDTO
{
    public function __construct(
        private bool $success,
        private ?\Symfony\Component\HttpFoundation\Response $response = null,
        private ?string $errorMessage = null,
        private ?int $writtenValuesCount = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getResponse(): ?\Symfony\Component\HttpFoundation\Response
    {
        return $this->response;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getWrittenValuesCount(): ?int
    {
        return $this->writtenValuesCount;
    }

    public static function success(
        \Symfony\Component\HttpFoundation\Response $response,
        int $writtenValuesCount
    ): self {
        return new self(true, $response, null, $writtenValuesCount);
    }

    public static function error(string $errorMessage): self
    {
        return new self(false, null, $errorMessage, null);
    }
}