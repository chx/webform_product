<?php

namespace Drupal\webform_product;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class WebFormProductFormHelper {

  static public function processElementForm(&$element, FormStateInterface $form_state, &$complete_form) {
    if (empty($element['#value'])) {
      return $element;
    }
    /** @var \Drupal\webform_ui\Form\WebformUiElementFormInterface  $formObject */
    $formObject = $form_state->getFormObject();
    $skuRoot = $formObject->getWebform()->id() . ':' . $formObject->getKey();
    $entityStorage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
    $query = $entityStorage->getQuery()->condition('type', 'webform');
    $skuCondition = $query
      ->orConditionGroup()
      ->condition('sku', $skuRoot)
      ->condition('sku', "$skuRoot:", 'STARTS_WITH');
    $ids = $query->condition($skuCondition)->execute();
    /** @var \Drupal\commerce_product\Entity\ProductVariation  $variation */
    foreach ($entityStorage->loadMultiple($ids) as $variation) {
      $complete_form['#webform_product_links'][$variation->getSku()] = [
        '#type' => 'link',
        '#title' => t('Price: @price', ['@price' => $variation->getPrice()]),
        '#url' => $variation->getProduct()->toUrl('edit-form'),
      ];
    }
    if (isset($complete_form['#webform_product_links'][$skuRoot])) {
      $complete_form['properties']['element']['webform_product'] = $complete_form['#webform_product_links'][$skuRoot];
    }
    else {
      $complete_form['properties']['element']['webform_product'] = [
        '#type' => 'link',
        '#url' => static::getUrl($skuRoot, $element['#value']),
        '#title' => t('Create product'),
      ];
    }
    return $element;
  }

  public static function submissionToCart(array &$form, FormStateInterface $form_state) {
    $sid = $form_state->getFormObject()->getEntity()->id();
    $skus = [];
    foreach (db_query('SELECT * FROM {webform_submission_data} WHERE sid = :sid', [':sid' => $sid]) as $row) {
      // The same SKU might be repeated.
      $skus[] = "$row->webform_id:$row->name";
      $skus[] = "$row->webform_id:$row->name:$row->value";
    }
    $variations = [];
    $variationIds = \Drupal::entityQuery('commerce_product_variation')
      ->condition('sku', $skus, 'IN')
      ->execute();
    foreach (ProductVariation::loadMultiple($variationIds) as $variation) {
    /** @var \Drupal\commerce_product\Entity\ProductVariation $variation */
      $variations[$variation->getSku()] = $variation;
      $stores = $variation->getStores();
    }
    if ($skus = array_intersect($skus, array_keys($variations))) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cartOrder */
      $cartOrder = \Drupal::service('commerce_cart.cart_provider')->createCart('default', reset($stores));
      /** @var \Drupal\commerce_cart\CartManager $cartManager */
      $cartManager = \Drupal::service('commerce_cart.cart_manager');
      foreach ($skus as $sku) {
        $cartManager->addEntity($cartOrder, $variations[$sku]);
      }
      $form_state->setRedirect('commerce_cart.page');
    }
  }

  /**
   * @param $sku
   * @param $title
   *
   * @return \Drupal\Core\Url
   */
  public static function getUrl($sku, $title) {
    $url = Url::fromRoute('webform_product.page', [
      // urlencoding is completely rotten: a single urlencoding will break the
      // path regexp in Router::doMatchCollection() when one of the arguments
      // contain a slash and double urlencoding breaks the CSRF checker...
      'sku' => base64_encode($sku),
      'title' => base64_encode($title),
    ]);
    return $url;
  }

}

