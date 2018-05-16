<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformToggles;

/**
 * Provides a 'price_toggles' element.
 *
 * @WebformElement(
 *   id = "price_toggles",
 *   label = @Translation("Price toggles"),
 *   description = @Translation("Provides a form element for toggling multiple on/off states."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceToggles extends WebformToggles {}
