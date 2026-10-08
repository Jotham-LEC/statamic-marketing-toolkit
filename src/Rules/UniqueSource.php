<?php

namespace JothamLec\MarketingToolkit\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JothamLec\MarketingToolkit\Redirects\Redirect;

/**
 * No other redirect on the same site starts from this address (in any letter
 * case, when matching ignores it).
 */
final readonly class UniqueSource implements ValidationRule
{
    /**
     * @param  ?string  $site  the rule's site; null: a rule for every site
     * @param  ?int  $ignoreId  the rule being edited, which may keep its own source
     * @param  ?bool  $taken  whether the caller already knows the answer (a CSV
     *                        import, which reads every rule once); null: look it up
     */
    public function __construct(private ?string $site = null, private ?int $ignoreId = null, private ?bool $taken = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->taken ?? Redirect::forSource((string) $value, $this->ignoreId, $this->site) !== null) {
            $fail(__('marketing-toolkit::validation.redirect.source_taken'));
        }
    }
}
