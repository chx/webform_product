<?php

/**
 * @file
 * Contains \Drupal\webform_product\Plugin\webform_product\WebformOptions.
 */


namespace Drupal\webform_product\Plugin\webform_product;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * @PluginID("webform_options")
 */
class WebformOptions {

  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['options']['#element']['price'] = [
      '#type' => 'textfield',
      '#title' => t('Price'),
      '#title_display' => 'invisible',
      '#placeholder' => '99.99',
      '#maxlength' => 20,
    ];

    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $form_state->getFormObject()->getWebForm();
    $prices = (array) $webform->getThirdPartySetting('webform_product', $webform->id() . '/' . $element['#name']);
    foreach ($element['options']['#default_value'] as $delta => $row) {
      if (isset($prices[$row['value']])) {
        $element['options']['#default_value'][$delta]['price'] = $prices[$row['value']];
      }
    }
    // WebFormOptions::convertValuesToOptions() destroys our values so do
    // something about that.
    array_unshift($element['#element_validate'], [get_class(), 'convertToSettings']);
    return $element;
  }

  public static function convertToSettings($element, FormStateInterface $form_state, $complete_form) {
    foreach ($element['options']['#value'] as $value) {
      if (!empty($value['price'])) {
        $option_value = $value['value'];
        $option_text = $value['text'];

        // Populate empty option value or option text.
        if ($option_value === '') {
          $option_value = $option_text;
        }
        $settings[$option_value] = $value['price'];
      }
      if (!empty($settings)) {
        /** @var \Drupal\webform\Entity\Webform $webform */
        $webform = $form_state->getFormObject()->getWebForm();
        $webform->setThirdPartySetting('webform_product', $webform->id() . '/' . $element['#name'], $settings);
      }
    }
  }

}
