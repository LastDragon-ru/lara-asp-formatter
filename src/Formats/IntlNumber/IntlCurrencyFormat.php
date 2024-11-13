<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Formatter\Formats\IntlNumber;

use IntlException;
use LastDragon_ru\LaraASP\Formatter\Formatter;
use NumberFormatter;
use Override;

use function is_array;

/**
 * @see NumberFormatter
 * @extends IntlFormat<IntlOptions, array{float|int|null, ?non-empty-string}|float|int|null>
 */
class IntlCurrencyFormat extends IntlFormat {
    /**
     * @param list<IntlOptions|null> $options
     */
    public function __construct(Formatter $formatter, array $options = []) {
        parent::__construct($formatter, [
            new IntlOptions(NumberFormatter::CURRENCY),
            ...$options,
        ]);
    }

    #[Override]
    public function __invoke(mixed $value): string {
        [$value, $currency] = is_array($value) ? $value : [$value, null];
        $value            ??= 0;
        $currency         ??= $this->formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);
        $formatted          = $this->formatter->formatCurrency($value, $currency);

        if ($formatted === false) {
            throw new IntlException($this->formatter->getErrorMessage(), $this->formatter->getErrorCode());
        }

        return $formatted;
    }
}
