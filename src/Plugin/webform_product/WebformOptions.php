<?php

namespace Drupal\webform_product\Plugin\webform_product;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_product\WebFormProductFormHelper;

/**
 * @PluginID("webform_options")
 */
class WebformOptions {

  public static function process(&$element, FormStateInterface $form_state) {
    $element['options']['#element']['price'] = [
      '#type' => 'textfield',
      '#title' => t('Price'),
      '#title_display' => 'invisible',
      '#placeholder' => '99.99',
      '#maxlength' => 20,
    ];
    if ($prices = WebFormProductFormHelper::getSetting($form_state, 'options')) {
      foreach ($element['options']['#default_value'] as $delta => $row) {
        if (isset($prices[$row['value']])) {
          $element['options']['#default_value'][$delta]['price'] = $prices[$row['value']];
        }
      }
    }
    // WebFormOptions::convertValuesToOptions() destroys our values so do
    // something about that.
    array_unshift($element['#element_validate'], [get_class(), 'convertToSettings']);
    return $element;
  }

  public static function convertToSettings($element, FormStateInterface $form_state) {
    foreach ($element['options']['#value'] as $value) {
      if (!empty($value['price'])) {
        $option_value = $value['value'];
        $option_text = $value['text'];

        // Populate empty option value or option text.
        if ($option_value === '') {
          $option_value = $option_text;
        }
        $prices[$option_value] = $value['price'];
      }
      if (!empty($prices)) {
        WebFormProductFormHelper::setSetting($form_state, 'options', $prices);
      }
    }
  }

}
