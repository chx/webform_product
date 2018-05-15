<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformButtons;

/**
 * Provides a 'buttons' element.
 *
 * @WebformElement(
 *   id = "price_webform_buttons",
 *   label = @Translation("Price buttons"),
 *   description = @Translation("Provides a group of multiple buttons used for selecting a value."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceWebformButtons extends WebformButtons {}
