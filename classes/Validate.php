<?php
// Small shared input checks. Throws InvalidArgumentException (the API turns it into a 400).
final class Validate {
    public static function positiveInt($value, string $label): int {
        if (is_bool($value)
            || filter_var($value, FILTER_VALIDATE_INT) === false
            || (int)$value < 1
            || (int)$value > 4294967295) {
            throw new InvalidArgumentException("$label must be a positive whole number.");
        }
        return (int)$value;
    }

    public static function money($value, string $label): float {
        if (!is_numeric($value) || (float)$value < 0 || (float)$value > 9999999999.99) {
            throw new InvalidArgumentException("$label must be a number from 0 up.");
        }
        return round((float)$value, 2);
    }

    public static function text($value, string $label, int $max): string {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("$label is required.");
        }
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("$label must be $max characters or fewer.");
        }
        return $value;
    }
}
