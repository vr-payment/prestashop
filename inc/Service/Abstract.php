<?php
/**
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Abstract implementation of services
 */
abstract class VRPaymentServiceAbstract
{
    private static $instances = array();

    /**
     *
     * @return static
     */
    public static function instance()
    {
        $class = get_called_class();
        if (! isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        $object = self::$instances[$class];

        return $object;
    }

    /**
     * Returns the fraction digits for the given currency.
     *
     * @param string $currencyCode
     * @return number
     */
    protected function getCurrencyFractionDigits($currencyCode)
    {
        return VRPaymentHelper::getCurrencyFractionDigits($currencyCode);
    }

    /**
     * Rounds the given amount to the currency's format.
     *
     * @param float $amount
     * @param string $currencyCode
     * @return number
     */
    protected function roundAmount($amount, $currencyCode)
    {
        return VRPaymentHelper::roundAmount($amount, $currencyCode);
    }

    /**
     * Returns the resource part of the resolved url
     *
     * @param String $resolved_url
     * @return string
     */
    protected function getResourcePath($resolvedUrl)
    {
        if (empty($resolvedUrl)) {
            return $resolvedUrl;
        }
        $index = strpos($resolvedUrl, 'resource/');
        return Tools::substr($resolvedUrl, $index + Tools::strlen('resource/'));
    }

    /**
     * Returns the base part of the resolved url
     *
     * @param String $resolved_url
     * @return string
     */
    protected function getResourceBase($resolvedUrl)
    {
        if (empty($resolvedUrl)) {
            return $resolvedUrl;
        }
        $parts = parse_url($resolvedUrl);
        return $parts['scheme'] . "://" . $parts['host'] . "/";
    }

    /**
     * Creates and returns a new entity filter.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $operator
     * @return \VRPayment\Sdk\Model\EntityQueryFilter
     */
    protected function createEntityFilter(
        $fieldName,
        $value,
        $operator = \VRPayment\Sdk\Model\CriteriaOperator::EQUALS
    ) {
        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::LEAF);
        $filter->setOperator($operator);
        $filter->setFieldName($fieldName);
        $filter->setValue($value);
        return $filter;
    }

    /**
     * Creates and returns a new entity order by.
     *
     * @param string $fieldName
     * @param string $sortOrder
     * @return \VRPayment\Sdk\Model\EntityQueryOrderBy
     */
    protected function createEntityOrderBy(
        $fieldName,
        $sortOrder = \VRPayment\Sdk\Model\EntityQueryOrderByType::DESC
    ) {
        $orderBy = new \VRPayment\Sdk\Model\EntityQueryOrderBy();
        $orderBy->setFieldName($fieldName);
        $orderBy->setSorting($sortOrder);
        return $orderBy;
    }

    /**
     * Changes the given string to have no more characters as specified.
     *
     * @param string $string
     * @param int $maxLength
     * @return string
     */
    protected function fixLength($string, $maxLength)
    {
        return Tools::substr((string) $string, 0, $maxLength, 'UTF-8');
    }

    /**
     * Removes all non printable ASCII chars
     *
     * @param string $string
     * @return string
     */
    protected function removeNonAscii($string)
    {
        return preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);
    }
}
