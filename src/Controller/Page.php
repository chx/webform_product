<?php

namespace Drupal\webform_product\Controller;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Controller\ControllerBase;
use Zend\Diactoros\Response\RedirectResponse;

class Page extends ControllerBase {

  public function page($sku, $title) {
    $sku = base64_decode($sku);
    $title = base64_decode($title);
    $entityStorage = $this->entityTypeManager()->getStorage('commerce_product_variation');
    $ids = $entityStorage
      ->getQuery()
      ->condition('type', 'webform')
      ->condition('sku', $sku)
      ->execute();
    if ($ids) {
      /** @var \Drupal\commerce_product\Entity\ProductVariation $variation */
      $variation = $entityStorage->load(reset($ids));
    }
    else {
      $variation = ProductVariation::create([
        'type' => 'webform',
        'sku' => $sku,
        'title' => $title,
      ]);
      $variation->save();
    }
    return new RedirectResponse($variation->toUrl('edit-form')->toString());
  }
}
