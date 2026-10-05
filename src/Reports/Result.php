<?php

namespace JothamLec\Seo\Reports;

/**
 * One check's verdict on one page.
 */
final readonly class Result
{
    public const string PASS = 'pass';

    public const string WARN = 'warn';

    public const string FAIL = 'fail';

    private function __construct(public string $status, public string $message) {}

    public static function pass(string $message = ''): self
    {
        return new self(self::PASS, $message);
    }

    public static function warn(string $message): self
    {
        return new self(self::WARN, $message);
    }

    public static function fail(string $message): self
    {
        return new self(self::FAIL, $message);
    }

    /**
     * Its share of the check's weight: all, half, or none.
     */
    public function value(): float
    {
        return match ($this->status) {
            self::PASS => 1.0,
            self::WARN => 0.5,
            default => 0.0,
        };
    }

    /**
     * @return array{status: string, message: string}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'message' => $this->message];
    }
}
