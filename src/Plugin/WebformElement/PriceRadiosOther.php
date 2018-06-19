<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformRadiosOther;

/**
 * Provides a 'price_radios_other' element.
 *
 * @WebformElement(
 *   id = "price_radios_other",
 *   label = @Translation("Price radios other"),
 *   description = @Translation("Provides a form element for a set of radio buttons, with the ability to enter a custom value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceRadiosOther extends WebformRadiosOther {}
