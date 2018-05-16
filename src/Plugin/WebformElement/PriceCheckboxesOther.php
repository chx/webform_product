<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformCheckboxesOther;

/**
 * Provides a 'price_checkboxes_other' element.
 *
 * @WebformElement(
 *   id = "price_checkboxes_other",
 *   label = @Translation("Price checkboxes other"),
 *   description = @Translation("Provides a form element for a set of checkboxes, with the ability to enter a custom value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceCheckboxesOther extends WebformCheckboxesOther {}
