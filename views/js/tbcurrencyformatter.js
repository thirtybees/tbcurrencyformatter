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
(function() {

  // resolve locale
  var locale = 'en';
  if (typeof window['full_language_code'] !== 'undefined') {
    locale = window['full_language_code'];
  } else {
    if (typeof navigator.languages !== 'undefined') {
      locale = navigator.languages[0];
    } else {
      locale = navigator.language;
    }
  }
  if (locale.length === 5) {
    locale = locale.substring(0, 2).toLowerCase() + '-' + locale.substring(3, 5).toUpperCase();
  }

  // currency formatter
  window.tbAutoCurrencyFormat = function(price, currencyFormat, currencySign, currencyBlank, priceDisplayPrecision) {
    try {
      price = ps_round(price, priceDisplayPrecision);

      // format number as an USD currency in specific locale, and then replace USD symbol with currencySign
      return price
        .toLocaleString(locale, {style: 'currency', currency: 'USD', currencyDisplay: 'code'})
        .replace('USD', currencySign || '')
        .trim();
    } catch (e) {
      console.warn("Failed to format currency", e);
      return null;
    }
  }
})();
