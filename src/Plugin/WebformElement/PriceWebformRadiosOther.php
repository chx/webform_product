<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformRadiosOther;

/**
 * Provides a 'radios_other' element.
 *
 * @WebformElement(
 *   id = "price_webform_radios_other",
 *   label = @Translation("Price radios other"),
 *   description = @Translation("Provides a form element for a set of radio buttons, with the ability to enter a custom value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceWebformRadiosOther extends WebformRadiosOther {}
