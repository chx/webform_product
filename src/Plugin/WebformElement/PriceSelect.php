<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\Select;

/**
 * Provides a 'select' element.
 *
 * @WebformElement(
 *   id = "price_select",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Select.php/class/Select",
 *   label = @Translation("Price select"),
 *   description = @Translation("Provides a form element for a drop-down menu or scrolling selection box."),
 *   category = @Translation("Price elements"),
 * )
 */
class PriceSelect extends Select {}
