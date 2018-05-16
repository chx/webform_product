<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\Checkboxes;

/**
 * Provides a 'price_checkboxes' element.
 *
 * @WebformElement(
 *   id = "price_checkboxes",
 *   label = @Translation("Price checkboxes"),
 *   description = @Translation("Provides a form element for a set of checkboxes."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceCheckboxes extends Checkboxes {}
