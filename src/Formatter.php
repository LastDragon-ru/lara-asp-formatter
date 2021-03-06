<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Formatter;

use Closure;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Traits\Macroable;
use IntlDateFormatter;
use LastDragon_ru\LaraASP\Core\Concerns\InstanceCache;
use Locale;
use NumberFormatter;
use OutOfBoundsException;

use function abs;
use function is_null;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function str_pad;
use function str_replace;
use function trim;

class Formatter {
    use Macroable;
    use InstanceCache;

    /**
     * Options:
     * - none
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()},
     *      except {@link NumberFormatter::FRACTION_DIGITS}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Integer = 'integer';

    /**
     * Options:
     * - none
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Scientific = 'scientific';

    /**
     * Options:
     * - none
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Spellout = 'spellout';

    /**
     * Options:
     * - none
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Ordinal = 'ordinal';

    /**
     * Options:
     * - none
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Duration = 'duration';

    /**
     * Options:
     * - `int`: fraction digits
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()},
     *      except {@link NumberFormatter::FRACTION_DIGITS}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Decimal = 'decimal';

    /**
     * Options:
     * - `string`: default currency
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()},
     *      except {@link NumberFormatter::FRACTION_DIGITS}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Currency = 'currency';

    /**
     * Options:
     * - `int`: fraction digits
     *
     * Locales options:
     * - 'intl_text_attributes' `array` {@link NumberFormatter::setTextAttribute()}
     * - 'intl_attributes' `array` {@link NumberFormatter::setAttribute()},
     *      except {@link NumberFormatter::FRACTION_DIGITS}
     * - 'intl_symbols' `array` {@link NumberFormatter::setSymbol()}
     */
    public const Percent = 'percent';

    /**
     * Options (one of):
     * - `int`: {@link \IntlDateFormatter::SHORT}, {@link \IntlDateFormatter::FULL},
     *      {@link \IntlDateFormatter::LONG} or {@link \IntlDateFormatter::MEDIUM}
     * - `string`: the name of custom format
     *
     * Locales options:
     * - `string`: available only for custom formats: locale specific pattern
     *      (key: `locales.<locale>.time.<format>` and/or default pattern
     *      (key: `all.time.<format>`)
     */
    public const Time = 'time';

    /**
     * @see Formatter::Time
     */
    public const Date = 'date';

    /**
     * @see Formatter::Time
     */
    public const DateTime = 'datetime';

    /**
     * Options:
     * - `int`: fraction digits
     *
     * Locales options:
     * - none
     */
    public const Filesize = 'filesize';

    /**
     * Options:
     * - `int`: how many characters should be shown
     *
     * Locales options:
     * - none
     */
    public const Secret = 'secret';

    protected Application $app;
    private string        $locale;

    public function __construct(Application $app) {
        $this->app    = $app;
        $this->locale = $this->getDefaultLocale();
    }

    // <editor-fold desc="Factory">
    // =========================================================================
    /**
     * Create a new formatter for the specified locale.
     */
    public function forLocale(string $locale): static {
        $formatter         = $this->app->make(static::class);
        $formatter->locale = $locale;

        return $formatter;
    }
    // </editor-fold>

    // <editor-fold desc="Getters & Setters">
    // =========================================================================
    public function getLocale(): string {
        return $this->locale;
    }

    // </editor-fold>

    // <editor-fold desc="Formats">
    // =========================================================================
    public function string(?string $value): string {
        return trim((string) $value);
    }

    public function integer(int|float|null $value): string {
        return $this
            ->getIntlNumberFormatter(static::Integer)
            ->format((float) $value);
    }

    public function decimal(?float $value, int $decimals = null): string {
        $type  = static::Decimal;
        $value = (float) $value;

        return $this
            ->getIntlNumberFormatter($type, $decimals, function () use ($type, $decimals): int {
                return $decimals ?: $this->getOptions($type, 2);
            })
            ->format($value);
    }

    public function currency(?float $value, string $currency = null): string {
        $type     = static::Currency;
        $currency = $currency ?: $this->getOptions($type, 'USD');

        return $this
            ->getIntlNumberFormatter($type)
            ->formatCurrency($value, $currency);
    }

    /**
     * @param float|null $value must be between 0-100
     */
    public function percent(?float $value, int $decimals = null): string {
        $type  = static::Percent;
        $value = $value / 100;

        return $this
            ->getIntlNumberFormatter($type, $decimals, function () use ($type, $decimals): int {
                return $decimals ?: $this->getOptions($type, 0);
            })
            ->format($value);
    }

    public function scientific(?float $value): string {
        return $this
            ->getIntlNumberFormatter(static::Scientific)
            ->format((float) $value);
    }

    public function spellout(?float $value): string {
        return $this
            ->getIntlNumberFormatter(static::Spellout)
            ->format((float) $value);
    }

    public function ordinal(?int $value): string {
        return $this
            ->getIntlNumberFormatter(static::Ordinal)
            ->format((int) $value);
    }

    public function duration(?int $value): string {
        return $this
            ->getIntlNumberFormatter(static::Duration)
            ->format((int) $value);
    }

    public function time(
        ?DateTimeInterface $value,
        string|int $format = null,
        DateTimeZone|string $tz = null,
    ): string {
        if (is_null($value)) {
            return '';
        }

        return $this
            ->getIntlDateFormatter(self::Time, $format, $tz)
            ->format($value);
    }

    public function date(
        ?DateTimeInterface $value,
        string|int $format = null,
        DateTimeZone|string $tz = null,
    ): string {
        if (is_null($value)) {
            return '';
        }

        return $this
            ->getIntlDateFormatter(self::Date, $format, $tz)
            ->format($value);
    }

    public function datetime(
        ?DateTimeInterface $value,
        string|int $format = null,
        DateTimeZone|string $tz = null,
    ): string {
        if (is_null($value)) {
            return '';
        }

        return $this
            ->getIntlDateFormatter(self::DateTime, $format, $tz)
            ->format($value);
    }

    public function filesize(?int $bytes, int $decimals = null): string {
        // Round
        $bytes    = (int) $bytes;
        $unit     = 0;
        $units    = [
            $this->getTranslation('filesize.bytes'),
            $this->getTranslation('filesize.KB'),
            $this->getTranslation('filesize.MB'),
            $this->getTranslation('filesize.GB'),
            $this->getTranslation('filesize.TB'),
            $this->getTranslation('filesize.PB'),
        ];
        $decimals = $decimals ?: $this->getOptions(static::Filesize, 2);

        while ($bytes >= 1024) {
            $bytes /= 1024;
            $unit++;
        }

        // Format
        return $unit === 0
            ? $this->integer($bytes).($bytes > 0 ? " {$units[$unit]}" : '')
            : $this->decimal($bytes, $decimals)." {$units[$unit]}";
    }

    public function secret(?string $value, int $show = null): string {
        if (is_null($value)) {
            return '';
        }

        $show   = $show ?: $this->getOptions(static::Secret, 5);
        $length = mb_strlen($value);
        $hidden = $length - $show;

        if ($length <= $show) {
            $value = str_pad('*', $length, '*');
        } elseif ($hidden < $show) {
            $value = str_replace(
                mb_substr($value, 0, $show),
                str_pad('*', $show, '*'),
                $value,
            );
        } else {
            $value = str_replace(
                mb_substr($value, 0, $hidden),
                str_pad('*', $hidden, '*'),
                $value,
            );
        }

        return $value;
    }
    // </editor-fold>

    // <editor-fold desc="Functions">
    // =========================================================================
    protected function getDefaultLocale(): string {
        return $this->app->getLocale() ?: Locale::getDefault();
    }

    protected function getDefaultTimezone(): string {
        return $this->app->make(Repository::class)->get('app.timezone') ?: 'UTC';
    }

    private function getIntlNumberFormatter(
        string $type,
        int $decimals = null,
        Closure $closure = null,
    ): NumberFormatter {
        return $this->getIntlFormatter(
            [$type, $decimals],
            function () use ($type, $decimals, $closure): ?NumberFormatter {
                $formatter  = null;
                $attributes = [];
                $symbols    = [];
                $texts      = [];

                if ($closure) {
                    $decimals = $closure($type, $decimals);
                }

                switch ($type) {
                    case static::Integer:
                        $formatter  = new NumberFormatter($this->getLocale(), NumberFormatter::DECIMAL);
                        $attributes = $attributes + [
                                NumberFormatter::FRACTION_DIGITS => 0,
                            ];
                        break;
                    case static::Decimal:
                        $formatter  = new NumberFormatter($this->getLocale(), NumberFormatter::DECIMAL);
                        $attributes = $attributes + [
                                NumberFormatter::FRACTION_DIGITS => abs((int) $decimals),
                            ];
                        break;
                    case static::Currency:
                        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::CURRENCY);
                        break;
                    case static::Percent:
                        $formatter  = new NumberFormatter($this->getLocale(), NumberFormatter::PERCENT);
                        $attributes = $attributes + [
                                NumberFormatter::FRACTION_DIGITS => abs((int) $decimals),
                            ];
                        break;
                    case static::Scientific:
                        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::SCIENTIFIC);
                        break;
                    case static::Spellout:
                        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::SPELLOUT);
                        break;
                    case static::Ordinal:
                        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::ORDINAL);
                        break;
                    case static::Duration:
                        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::DURATION);
                        break;
                    default:
                        // null
                        break;
                }

                if ($formatter) {
                    $attributes = $attributes
                        + (array) $this->getLocaleOptions($type, 'intl_attributes')
                        + (array) $this->getOptions('intl_attributes');
                    $symbols    = $symbols
                        + (array) $this->getLocaleOptions($type, 'intl_symbols')
                        + (array) $this->getOptions('intl_symbols');
                    $texts      = $texts
                        + (array) $this->getLocaleOptions($type, 'intl_text_attributes')
                        + (array) $this->getOptions('intl_text_attributes');

                    foreach ($attributes as $attribute => $value) {
                        if (!$formatter->setAttribute($attribute, $value)) {
                            throw new OutOfBoundsException(
                                "\\NumberFormatter::setAttribute() failed: '{$attribute}' is unknown/invalid.",
                            );
                        }
                    }

                    foreach ($symbols as $symbol => $value) {
                        if (!$formatter->setSymbol($symbol, $value)) {
                            throw new OutOfBoundsException(
                                "\\NumberFormatter::setSymbol() failed: '{$symbol}' is unknown/invalid.",
                            );
                        }
                    }

                    foreach ($texts as $text => $value) {
                        if (!$formatter->setTextAttribute($text, $value)) {
                            throw new OutOfBoundsException(
                                "\\NumberFormatter::setTextAttribute() failed: '{$text}' is unknown/invalid.",
                            );
                        }
                    }
                }

                return $formatter;
            },
        );
    }

    private function getIntlDateFormatter(
        string $type,
        string|int $format = null,
        DateTimeZone|string $tz = null,
    ): IntlDateFormatter {
        return $this->getIntlFormatter(
            [$type, $format, $tz],
            function () use ($type, $format, $tz): ?IntlDateFormatter {
                $formatter = null;
                $pattern   = '';
                $format    = $format ?: $this->getOptions($type, IntlDateFormatter::SHORT);
                $tz        = $this->getTimezone($tz);

                if (is_string($format)) {
                    $pattern = (string) $this->getLocaleOptions($type, $format);
                    $format  = IntlDateFormatter::FULL;
                }

                switch ($type) {
                    case self::Time:
                        $formatter = new IntlDateFormatter(
                            $this->getLocale(),
                            IntlDateFormatter::NONE,
                            $format,
                            $tz,
                            null,
                            $pattern,
                        );
                        break;
                    case self::Date:
                        $formatter = new IntlDateFormatter(
                            $this->getLocale(),
                            $format,
                            IntlDateFormatter::NONE,
                            $tz,
                            null,
                            $pattern,
                        );
                        break;
                    case self::DateTime:
                        $formatter = new IntlDateFormatter(
                            $this->getLocale(),
                            $format,
                            $format,
                            $tz,
                            null,
                            $pattern,
                        );
                        break;
                    default:
                        // empty
                        break;
                }

                return $formatter;
            },
        );
    }

    private function getIntlFormatter(mixed $type, Closure $closure): NumberFormatter|IntlDateFormatter {
        return $this->instanceCacheGet([__METHOD__, $type], $closure);
    }

    protected function getOptions(string $type, mixed $default = null): mixed {
        $package = Package::Name;
        $config  = $this->app->make(Repository::class);
        $key     = "{$package}.options.{$type}";

        return $config->get($key, $default);
    }

    protected function getLocaleOptions(string $type, string $option): mixed {
        $package = Package::Name;
        $locale  = $this->getLocale();
        $config  = $this->app->make(Repository::class);
        $pattern = null
            ?? $config->get("{$package}.locales.{$locale}.{$type}.{$option}")
            ?? $config->get("{$package}.all.{$type}.{$option}");

        return $pattern;
    }

    /**
     * @param array<string, mixed> $replace
     */
    protected function getTranslation(string $key, array $replace = []): string {
        $translator  = $this->app->make(PackageTranslator::class);
        $translation = $translator->get($key, $replace, $this->getLocale());

        return $translation;
    }

    protected function getTimezone(DateTimeZone|string $tz = null): ?DateTimeZone {
        if (is_null($tz)) {
            $tz = $this->getDefaultTimezone();
        }

        if (is_string($tz)) {
            $tz = new DateTimeZone($tz);
        }

        return $tz;
    }
    // </editor-fold>
}
