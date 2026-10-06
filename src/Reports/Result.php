<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Support\Facades\Lang;

/**
 * One check's verdict on one page. Its message is a translation key and its
 * parameters, kept as they are and translated when shown, so a report reads
 * in the language of whoever opens it. Plain text works as well: a site's
 * own rules may pass English, and older reports hold nothing else.
 *
 * A parameter may itself be a message, as `['message' => ..., 'params' => [...]]`
 * (a list that ends "and 3 more"), translated along with the message.
 */
final readonly class Result
{
    public const string PASS = 'pass';

    public const string WARN = 'warn';

    public const string FAIL = 'fail';

    /**
     * @param  array<string, mixed>  $params
     */
    private function __construct(public string $status, public string $message, public array $params = []) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public static function pass(string $message = '', array $params = []): self
    {
        return new self(self::PASS, $message, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function warn(string $message, array $params = []): self
    {
        return new self(self::WARN, $message, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function fail(string $message, array $params = []): self
    {
        return new self(self::FAIL, $message, $params);
    }

    /**
     * The message in the current language.
     */
    public function text(): string
    {
        return self::translate($this->message, $this->params);
    }

    /**
     * A stored message in the current language. A `count` parameter picks the
     * plural form, if the language has one for that message.
     *
     * @param  array<string, mixed>  $params
     */
    public static function translate(string $message, array $params = []): string
    {
        $params = array_map(
            fn ($value) => is_array($value) ? self::translate((string) ($value['message'] ?? ''), (array) ($value['params'] ?? [])) : $value,
            $params,
        );

        // Only a known key is split on `|`: plain text may hold one.
        if (isset($params['count']) && is_numeric($params['count']) && Lang::has($message)) {
            return trans_choice($message, (float) $params['count'], $params);
        }

        return (string) __($message, $params);
    }

    /**
     * As a page's results column keeps it; `params` only when there are some.
     *
     * @return array{status: string, message: string, params?: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'message' => $this->message, ...($this->params === [] ? [] : ['params' => $this->params])];
    }
}
