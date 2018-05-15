<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformToggles;

/**
 * Provides a 'toggles' element.
 *
 * @WebformElement(
 *   id = "price_webform_toggles",
 *   label = @Translation("Price toggles"),
 *   description = @Translation("Provides a form element for toggling multiple on/off states."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceWebformToggles extends WebformToggles {}
