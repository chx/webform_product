<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\Checkboxes;

/**
 * Provides a 'checkboxes' element.
 *
 * @WebformElement(
 *   id = "price_checkboxes",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Checkboxes.php/class/Checkboxes",
 *   label = @Translation("Price checkboxes"),
 *   description = @Translation("Provides a form element for a set of checkboxes."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceCheckboxes extends Checkboxes {}
