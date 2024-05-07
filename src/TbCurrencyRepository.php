<?php
/**
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace TbCurrencyFormatter;

use CommerceGuys\Intl\Currency\Currency;
use CommerceGuys\Intl\Currency\CurrencyRepositoryInterface;

class TbCurrencyRepository implements CurrencyRepositoryInterface
{
    /**
     * @var CurrencyRepositoryInterface
     */
    private $repository;

    /**
     * @var array[]
     */
    private $symbols;


    /**
     * @var Currency
     */
    private $currencies = [];

    /**
     * TbCurrencyRepository constructor.
     * @param CurrencyRepositoryInterface $repository
     * @param array[] $currencies
     */
    public function __construct($repository, $currencies)
    {
        $this->repository = $repository;
        $this->symbols = [];
        foreach ($currencies as $currency) {
            if ($currency['sign']) {
                $iso = mb_strtoupper($currency['iso_code']);
                $this->symbols[$iso] = $currency['sign'];
            }
        }
    }


    /**
     * Gets a currency matching the provided currency code.
     *
     * @param string $currencyCode The currency code.
     * @param string $locale The locale (i.e. fr-FR).
     *
     * @return Currency
     */
    public function get($currencyCode, $locale = null)
    {
        $currencyCode = mb_strtoupper($currencyCode);
        $key = $currencyCode . '|' . ($locale ? $locale : 'any');
        if (! isset($this->currencies[$key])) {
            $currency = $this->repository->get($currencyCode, $locale);
            if (isset($this->symbols[$currencyCode])) {
                // adjust currency -- inject thirty bees currency symbol
                $currency = new Currency([
                    'symbol' => $this->symbols[$currencyCode],
                    'currency_code' => $currency->getCurrencyCode(),
                    'name' => $currency->getName(),
                    'numeric_code' => $currency->getNumericCode(),
                    'fraction_digits' => $currency->getFractionDigits(),
                    'locale' => $currency->getLocale()
                ]);
            }
            $this->currencies[$key] = $currency;
        }
        return $this->currencies[$key];
    }

    /**
     * Gets all currencies.
     *
     * @param string $locale The locale (i.e. fr-FR).
     *
     * @return Currency[] An array of currencies, keyed by currency code.
     */
    public function getAll($locale = null)
    {
        return $this->repository->getAll($locale);
    }

    /**
     * Gets a list of currencies.
     *
     * @param string $locale The locale (i.e. fr-FR).
     *
     * @return string[] An array of currency names, keyed by currency code.
     */
    public function getList($locale = null)
    {
        return $this->repository->getList($locale);
    }
}
