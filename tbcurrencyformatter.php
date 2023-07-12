<?php
/**
 * Copyright (C) 2021-2021 thirty bees
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
 * @copyright 2021 - 2021 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

use CommerceGuys\Intl\Currency\CurrencyRepository;
use CommerceGuys\Intl\Currency\CurrencyRepositoryInterface;
use CommerceGuys\Intl\Formatter\CurrencyFormatter;
use CommerceGuys\Intl\NumberFormat\NumberFormatRepository;
use TbCurrencyFormatter\TbCurrencyRepository;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

class TbCurrencyFormatter extends Module
{
    /**
     * @var CurrencyFormatter
     */
    private $formatter;

    /**
     * @var CurrencyRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var array[]
     */
    private $currencies;


    /**
     * TbCurrencyFormatter constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'tbcurrencyformatter';
        $this->tab = 'administration';
        $this->version = '1.0.1';
        $this->author = 'thirty bees';
        $this->controllers = [];
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Auto Currency Formatter');
        $this->description = $this->l('Automatic currency formatting by the CommerceGuys library.');
        $this->need_instance = 0;
        $this->tb_versions_compliancy = '>= 1.3.0';
        $this->tb_min_version = '1.3.0';
    }

    /**
     * Module installation process
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('actionGetCurrencyFormatters') &&
            $this->registerHook('actionAdminCurrenciesFormModifier') &&
            $this->registerHook('actionAdminCurrenciesControllerSaveAfter') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayBackOfficeHeader')
        );
    }

    /**
     * Header hook handler
     */
    public function hookDisplayHeader()
    {
        $this->context->controller->addJs($this->_path.'views/js/'.$this->name.'.js');
    }

    /**
     * Back office header hook handler
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addJs($this->_path.'views/js/'.$this->name.'.js');
    }

    /**
     * Hook to register currency formatters
     *
     * Return list of currencies with 'Auto Format' mode enabled
     *
     * @param array $params
     * @return array
     * @throws PrestaShopException
     */
    public function hookActionGetCurrencyFormatters($params)
    {
        $this->currencies = $params['currencies'];
        $formatters = [];
        foreach ($this->currencies as $currency) {
            $id = (int)$currency['id_currency'];
            $disableAutoFormat = !!Configuration::get('TB_NO_AUTO_FORMAT_'.$id);
            if (! $disableAutoFormat) {
                $formatters[$id] = [
                    'php' => [$this, 'formatCurrency'],
                    'js' => 'tbAutoCurrencyFormat'
                ];
            }
        }
        return $formatters;
    }

    /**
     * Hook called when currency is saved in administration.
     * Used to save 'Auto Format' option to configuration table
     *
     * @param $params
     *
     * @throws PrestaShopException
     */
    public function hookActionAdminCurrenciesControllerSaveAfter($params)
    {
        $noAutoFormat = Tools::getValue('auto_format') ? 0 : 1;
        $currencyId = $params['controller']->object->id;
        Configuration::updateValue('TB_NO_AUTO_FORMAT_' . $currencyId, $noAutoFormat);
    }

    /**
     * Hook to extend currency entry form
     *
     * @param $params
     * @throws PrestaShopException
     */
    public function hookActionAdminCurrenciesFormModifier($params)
    {
        $params['fields'][] = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Auto currency formatter'),
                    'icon'  => 'icon-money',
                ],
                'input' => [
                    [
                        'type'     => 'switch',
                        'label'    => $this->l('Auto Format'),
                        'name'     => 'auto_format',
                        'required' => false,
                        'is_bool'  => true,
                        'values'   => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                        'desc'     => $this->l('Turn on automatic formatting by the CommerceGuys library. In addition to \'Decimals\' and \'Spacing\' above, this also ignores the number of decimals configured in general preferences.'),
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ]
            ]
        ];

        $isoCode = $params['fields_value']['iso_code'];
        $currencyId = Currency::getIdByIsoCode($isoCode);

        /** @noinspection PhpArrayWriteIsNotUsedInspection */
        $params['fields_value']['auto_format'] = !Configuration::get('TB_NO_AUTO_FORMAT_'.$currencyId);
    }

    /**
     * Format currency callback
     *
     * This method is called by php code, see Tools::displayPrice
     *
     * @param float $price
     * @param Currency $currency
     * @param Language $language
     * @return string
     */
    public function formatCurrency($price, $currency, $language)
    {
        try {
            return $this->formatCurrencyInner($price, $currency, $language);
        } catch (Exception $e) {
            return null;
        }
    }


    /**
     * Format currency, may throw exception
     *
     * @param float $price
     * @param Currency $currency
     * @param Language $language
     * @return string|null
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function formatCurrencyInner($price, $currency, $language)
    {
        $currencyIso = mb_strtoupper($currency->iso_code);

        // check that currency exists
        if ($this->currencyExists($currencyIso)) {
            $price = Tools::ps_round($price, $this->getCurrencyDisplayPrecision($currency));
            $languageIso = $language->language_code;
            return $this->getFormatter()->format($price, $currencyIso, ['locale' => $languageIso]);
        }

        return null;
    }

    /**
     * returns currency formatter
     *
     * @return CurrencyFormatter
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getFormatter()
    {
        if (is_null($this->formatter)) {
            $this->formatter = new CurrencyFormatter(
                new NumberFormatRepository(),
                $this->getCurrencyRepository()

            );
        }
        return $this->formatter;
    }

    /**
     * Returns or creates currency repository
     *
     * @return CurrencyRepositoryInterface
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getCurrencyRepository()
    {
        if (is_null($this->currencyRepository)) {
            $this->currencyRepository = new TbCurrencyRepository(
                new CurrencyRepository(),
                $this->getCurrencies()
            );
        }
        return $this->currencyRepository;
    }

    /**
     * Returns or loads all currencies used by thirty bees store
     *
     * @return array[]
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getCurrencies()
    {
        if (is_null($this->currencies)) {
            $this->currencies = Currency::getCurrencies(false, false);
        }
        return $this->currencies;
    }

    /**
     * Returns true if currency exists
     *
     * @param string $currencyIso
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function currencyExists($currencyIso)
    {
        return !!$this->getCurrencyRepository()->get($currencyIso);
    }

    /**
     * @param Currency $currency
     *
     * @return int
     * @throws PrestaShopException
     */
    protected function getCurrencyDisplayPrecision($currency)
    {
        if (method_exists($currency, 'getDisplayPrecision')) {
            return $currency->getDisplayPrecision();
        }
        if ($currency->decimals) {
            return (int)Configuration::get('PS_PRICE_DISPLAY_PRECISION');
        }
        return 0;
    }
}
