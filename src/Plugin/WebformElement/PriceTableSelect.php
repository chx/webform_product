<?php

namespace Drupal\webform_product\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\TableSelect;

/**
 * Provides a 'price_tableselect' element.
 *
 * @WebformElement(
 *   id = "price_tableselect",
 *   label = @Translation("Price table select"),
 *   description = @Translation("Provides a form element for a table with radios or checkboxes in left column."),
 *   category = @Translation("Price elements"),
 *   states_wrapper = TRUE,
 * )
 */
class PriceTableSelect extends TableSelect {}
