<?php

namespace MacropaySolutions\Kernel\Support;

use MacropaySolutions\Kernel\Macroable\Contracts\Macroable;
use NumberFormatter;
use RuntimeException;

class Number implements Macroable
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelSupportNumber;

    /**
     * The current default locale.
     *
     * @var string
     */
    protected static $locale = 'en';

    /**
     * Format the given number according to the current locale.
     *
     * @param int|float $number
     * @param int|null $precision
     * @param int|null $maxPrecision
     * @param string|null $locale
     * @return string|false
     */
    public static function format(
        int|float $number,
        ?int $precision = null,
        ?int $maxPrecision = null,
        ?string $locale = null
    ) {
        static::ensureIntlExtensionIsInstalled();

        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::DECIMAL);

        if (!is_null($maxPrecision)) {
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        } elseif (!is_null($precision)) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
        }

        return $formatter->format($number);
    }

    /**
     * Spell out the given number in the given locale.
     *
     * @param int|float $number
     * @param string|null $locale
     * @param int|null $after
     * @param int|null $until
     * @return string
     */
    public static function spell(int|float $number, ?string $locale = null, ?int $after = null, ?int $until = null)
    {
        static::ensureIntlExtensionIsInstalled();

        if (!is_null($after) && $number <= $after) {
            return static::format($number, locale: $locale);
        }

        if (!is_null($until) && $number >= $until) {
            return static::format($number, locale: $locale);
        }

        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::SPELLOUT);

        return $formatter->format($number);
    }

    /**
     * Convert the given number to ordinal form.
     *
     * @param int|float $number
     * @param string|null $locale
     * @return string
     */
    public static function ordinal(int|float $number, ?string $locale = null)
    {
        static::ensureIntlExtensionIsInstalled();

        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::ORDINAL);

        return $formatter->format($number);
    }

    /**
     * Convert the given number to its percentage equivalent.
     *
     * @param int|float $number
     * @param int $precision
     * @param int|null $maxPrecision
     * @param string|null $locale
     * @return string|false
     */
    public static function percentage(
        int|float $number,
        int $precision = 0,
        ?int $maxPrecision = null,
        ?string $locale = null
    ) {
        static::ensureIntlExtensionIsInstalled();

        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::PERCENT);

        if (!is_null($maxPrecision)) {
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        } else {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
        }

        return $formatter->format($number / 100);
    }

    /**
     * Convert the given number to its currency equivalent.
     *
     * @param int|float $number
     * @param string $in
     * @param string|null $locale
     * @return string|false
     */
    public static function currency(int|float $number, string $in = 'USD', ?string $locale = null)
    {
        static::ensureIntlExtensionIsInstalled();

        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($number, $in);
    }

    /**
     * Convert the given number to its file size equivalent.
     *
     * @param int|float $bytes
     * @param int $precision
     * @param int|null $maxPrecision
     * @return string
     */
    public static function fileSize(int|float $bytes, int $precision = 0, ?int $maxPrecision = null)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        for ($i = 0; ($bytes / 1024) > 0.9 && ($i < count($units) - 1); $i++) {
            $bytes /= 1024;
        }

        return sprintf('%s %s', static::format($bytes, $precision, $maxPrecision), $units[$i]);
    }

    /**
     * Convert the number to its human-readable equivalent.
     *
     * @param int|float $number
     * @param int $precision
     * @param int|null $maxPrecision
     * @return bool|string
     */
    public static function abbreviate(int|float $number, int $precision = 0, ?int $maxPrecision = null)
    {
        return static::forHumans($number, $precision, $maxPrecision, abbreviate: true);
    }

    /**
     * Convert the number to its human-readable equivalent.
     *
     * @param int|float $number
     * @param int $precision
     * @param int|null $maxPrecision
     * @param bool $abbreviate
     * @return bool|string
     */
    public static function forHumans(
        int|float $number,
        int $precision = 0,
        ?int $maxPrecision = null,
        bool $abbreviate = false
    ) {
        return static::summarize(
            $number,
            $precision,
            $maxPrecision,
            $abbreviate ? [
                3 => 'K',
                6 => 'M',
                9 => 'B',
                12 => 'T',
                15 => 'Q',
            ] : [
                3 => ' thousand',
                6 => ' million',
                9 => ' billion',
                12 => ' trillion',
                15 => ' quadrillion',
            ]
        );
    }

    /**
     * Convert the number to its human-readable equivalent.
     *
     * @param int|float $number
     * @param int $precision
     * @param int|null $maxPrecision
     * @param array $units
     * @return string|false
     */
    protected static function summarize(
        int|float $number,
        int $precision = 0,
        ?int $maxPrecision = null,
        array $units = []
    ) {
        if ([] === $units) {
            $units = [
                3 => 'K',
                6 => 'M',
                9 => 'B',
                12 => 'T',
                15 => 'Q',
            ];
        }

        switch (true) {
            case floatval($number) === 0.0:
                return $precision > 0 ? static::format(0, $precision, $maxPrecision) : '0';
            case $number < 0:
                return sprintf('-%s', static::summarize(abs($number), $precision, $maxPrecision, $units));
            case $number >= 1e15:
                return sprintf(
                    '%s' . end($units),
                    static::summarize($number / 1e15, $precision, $maxPrecision, $units)
                );
        }

        $numberExponent = floor(log10($number));
        $displayExponent = $numberExponent - ($numberExponent % 3);
        $number /= pow(10, $displayExponent);

        return trim(
            sprintf('%s%s', static::format($number, $precision, $maxPrecision), $units[$displayExponent] ?? '')
        );
    }

    /**
     * Clamp the given number between the given minimum and maximum.
     *
     * @param int|float $number
     * @param int|float $min
     * @param int|float $max
     * @return int|float
     */
    public static function clamp(int|float $number, int|float $min, int|float $max)
    {
        return min(max($number, $min), $max);
    }

    /**
     * Plain decimal notation, without exponent ("1.0E-7" -> "0.0000001"), for bcmath and raw SQL.
     *
     * @throws \InvalidArgumentException
     */
    public static function toDecimalString(int|float|string $number): string
    {
        if (\is_int($number)) {
            return (string)$number;
        }

        $string = \trim((string)$number);

        if ((\is_float($number) && !\is_finite($number)) || !\is_numeric($string)) {
            throw new \InvalidArgumentException('Not a finite number: ' . $string);
        }

        [$mantissa, $exponent] = \array_pad(\explode('e', \strtolower($string), 2), 2, '0');
        $exponent = (int)$exponent;

        if (\abs($exponent) > 1000) {
            throw new \InvalidArgumentException('Exponent outside of the allowed range: ' . $string);
        }

        $sign = '-' === $mantissa[0] ? '-' : '';
        [$integer, $fraction] = \array_pad(\explode('.', \ltrim($mantissa, '+-'), 2), 2, '');
        $digits = $integer . $fraction;
        $point = \strlen($integer) + $exponent;

        $result = match (true) {
            $point <= 0 => '0.' . \str_repeat('0', -$point) . $digits,
            $point >= \strlen($digits) => $digits . \str_repeat('0', $point - \strlen($digits)),
            default => \substr($digits, 0, $point) . '.' . \substr($digits, $point),
        };

        if (\str_contains($result, '.')) {
            $result = \rtrim(\rtrim($result, '0'), '.');
        }

        $result = \ltrim($result, '0');
        $result = '' === $result || '.' === $result[0] ? '0' . $result : $result;

        return '0' === $result ? '0' : $sign . $result;
    }

    /**
     * Execute the given callback using the given locale.
     *
     * @param string $locale
     * @param callable $callback
     * @return mixed
     */
    public static function withLocale(string $locale, callable $callback)
    {
        $previousLocale = static::$locale;

        static::useLocale($locale);

        return tap($callback(), fn() => static::useLocale($previousLocale));
    }

    /**
     * Set the default locale.
     *
     * @param string $locale
     * @return void
     */
    public static function useLocale(string $locale)
    {
        static::$locale = $locale;
    }

    /**
     * Ensure the "intl" PHP extension is installed.
     *
     * @return void
     */
    protected static function ensureIntlExtensionIsInstalled()
    {
        if (!extension_loaded('intl')) {
            $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

            throw new RuntimeException('The "intl" PHP extension is required to use the [' . $method . '] method.');
        }
    }
}
