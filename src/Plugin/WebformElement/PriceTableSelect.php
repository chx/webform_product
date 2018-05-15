<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\TableSelect;

/**
 * Provides a 'tableselect' element.
 *
 * @WebformElement(
 *   id = "price_tableselect",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Tableselect.php/class/Tableselect",
 *   label = @Translation("Price table select"),
 *   description = @Translation("Provides a form element for a table with radios or checkboxes in left column."),
 *   category = @Translation("Price elements"),
 *   states_wrapper = TRUE,
 * )
 */
class PriceTableSelect extends TableSelect {}
