<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformSelectOther;

/**
 * Provides a 'select_other' element.
 *
 * @WebformElement(
 *   id = "price_webform_select_other",
 *   label = @Translation("Price select other"),
 *   description = @Translation("Provides a form element for a drop-down menu or scrolling selection box, with the ability to enter a custom value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceWebformSelectOther extends WebformSelectOther {}
