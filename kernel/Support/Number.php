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

        return $formatter->format(static::withoutNegativeZero($number, $formatter->format(...)));
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

        return $formatter->format(static::withoutNegativeZero($number / 100, $formatter->format(...)));
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

        return $formatter->formatCurrency(
            static::withoutNegativeZero(
                $number,
                static fn(int|float $value) => $formatter->formatCurrency($value, $in)
            ),
            $in
        );
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
        if (!\is_finite($bytes)) {
            return \sprintf('%s B', static::format($bytes, $precision, $maxPrecision));
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        // abs(): negative sizes were never scaled (-1024 B)
        for ($i = 0; (\abs($bytes) / 1024) > 0.9 && ($i < \count($units) - 1); $i++) {
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
        // log10() of INF / NAN never reaches a unit: INF looped forever
        if (!\is_finite($number)) {
            return static::format($number, $precision, $maxPrecision);
        }

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
                $summary = static::summarize(\abs($number), $precision, $maxPrecision, $units);

                return $summary === static::summarize(0, $precision, $maxPrecision, $units)
                    ? $summary
                    : \sprintf('-%s', $summary);
            case $number >= 1e15:
                return sprintf(
                    '%s' . end($units),
                    static::summarize($number / 1e15, $precision, $maxPrecision, $units)
                );
        }

        $numberExponent = floor(log10($number));
        // below 1 the exponent is negative: 0.001 was scaled up to "1"
        $displayExponent = \max(0, $numberExponent - ($numberExponent % 3));
        $number /= pow(10, $displayExponent);
        $formatted = static::format($number, $precision, $maxPrecision);

        // 999,999 rounds to "1,000" thousand: move to the next unit
        if (
            $formatted === static::format(1000, $precision, $maxPrecision)
            && isset($units[$displayExponent + 3])
        ) {
            $number /= 1000;
            $displayExponent += 3;
            $formatted = static::format($number, $precision, $maxPrecision);
        }

        return \trim(\sprintf('%s%s', $formatted, $units[$displayExponent] ?? ''));
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

        try {
            return $callback();
        } finally {
            static::useLocale($previousLocale);
        }
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
     * A negative number that formats like zero is formatted as zero, not "-0".
     */
    private static function withoutNegativeZero(int|float $number, callable $format): int|float
    {
        return $number < 0 && $format(\abs($number)) === $format(0) ? 0 : $number;
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
