<?php

namespace App\Exceptions;

use Exception;

class KycException extends Exception
{
    public static function stepNotAccessible(int $step): self
    {
        return new self("Step {$step} is not accessible. Please complete previous steps first.");
    }

    public static function fileUploadFailed(string $reason = ''): self
    {
        return new self("File upload failed. {$reason}");
    }

    public static function invalidDocumentType(): self
    {
        return new self("Invalid document type provided.");
    }

    public static function applicationNotFound(): self
    {
        return new self("KYC application not found.");
    }

    public static function documentNotFound(): self
    {
        return new self("Document not found.");
    }
}