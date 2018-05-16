<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformSelectOther;

/**
 * Provides a 'price_select_other' element.
 *
 * @WebformElement(
 *   id = "price_select_other",
 *   label = @Translation("Price select other"),
 *   description = @Translation("Provides a form element for a drop-down menu or scrolling selection box, with the ability to enter a custom value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceSelectOther extends WebformSelectOther {}
