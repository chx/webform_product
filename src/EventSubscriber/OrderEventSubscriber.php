<?php

namespace Drupal\webform_product\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Url;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\webform_product\Controller\WebformProductController;
use Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class OrderEventSubscriber.
 *
 * @package Drupal\webform_product\ProductEventSubscriber
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.validate.post_transition' => ['onOrderValidatePostTransition'],
    ];
    return $events;
  }

  /**
   * Post Transition; Place (from Draft to Validation).
   *
   * Execute Webform Submission Handlers on Validate transition, when a payment
   * has been validated by the payment provider.
   *
   * This will only be triggered if the submission is initialized.
   *
   * @todo Add validate state to the submission status field.
   * @todo Make use of the workflow labels, instead of custom labels.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onOrderValidatePostTransition(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();

    if (!$order->hasField(WebformProductWebformHandler::FIELD_LINK_ORDER_ORIGIN)) {
      return;
    }

    $source_uri = $order->get(WebformProductWebformHandler::FIELD_LINK_ORDER_ORIGIN)->getValue();
    $params = Url::fromUri($source_uri[0]['uri'])->getRouteParameters();

    /** @var \Drupal\webform\WebformSubmissionInterface $webformSubmission */
    $webformSubmission = \Drupal::entityTypeManager()->getStorage('webform_submission')->load($params['webform_submission']);
    $handlers = $webformSubmission->getWebform()->getHandlers('webform_product');

    $config = $handlers->getConfiguration();
    if (!$config) {
      return;
    }

    /** @var \Drupal\webform\Plugin\WebformHandlerInterface $handler */
    $handler = reset($config);
    $settings = $handler['settings'];

    $status = $webformSubmission->getElementData($settings[WebformProductWebformHandler::FIELD_STATUS]);

    // Complete submission if this hasn't been done.
    // There is no need for an access check, because the transition will check.
    if ($status && $status === WebformProductController::PAYMENT_STATUS_INITIALIZED) {

      // Disable the webform draft state, to mark the payment as completed.
      // Set the webform 'completed' state, to trigger webform handlers such as
      // Exact and Email.
      $webformSubmission
        ->setElementData($settings[WebformProductWebformHandler::FIELD_STATUS], WebformProductController::PAYMENT_STATUS_COMPLETED)
        ->set('in_draft', FALSE)
        ->set('completed', TRUE)
        ->save();

      $this->getLogger('webform_product')->notice('Finalized Webform Submission %sid on payment', [
        '%sid' => $webformSubmission->id(),
      ]);
    }
  }

}
