<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformButtonsOther;

/**
 * Provides a 'price_buttons_other' element.
 *
 * @WebformElement(
 *   id = "price_buttons_other",
 *   label = @Translation("Price buttons other"),
 *   description = @Translation("Provides a group of multiple buttons used for selecting a value, with the ability to enter a custom value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceButtonsOther extends WebformButtonsOther {}
