<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Helper;

/**
 * Class FloatComparator
 *
 * Helper to compare floats
 */
class FloatComparator
{
    /**
     * Precision for floats comparing.
     *
     * @var float
     */
    private static $epsilon = 0.00001;

    /**
     * Compares two float digits.
     *
     * @param float $a
     * @param float $b
     * @return bool
     * @since 101.0.6
     */
    public function equal(float $a, float $b): bool
    {
        return abs($a - $b) <= self::$epsilon;
    }

    /**
     * Compares if the first argument greater than the second argument.
     *
     * @param float $a
     * @param float $b
     * @return bool
     * @since 101.0.6
     */
    public function greaterThan(float $a, float $b): bool
    {
        return ($a - $b) > self::$epsilon;
    }

    /**
     * Compares if the first argument greater or equal to the second.
     *
     * @param float $a
     * @param float $b
     * @return bool
     * @since 101.0.6
     */
    public function greaterThanOrEqual(float $a, float $b): bool
    {
        return $this->equal($a, $b) || $this->greaterThan($a, $b);
    }
}
