<?php

namespace Drupal\webform_product\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides route responses for webform product.
 *
 * @todo Dependency injection.
 */
class WebformProductController extends ControllerBase implements ContainerInjectionInterface {

  use MessengerTrait;

  // Payment statuses.
  const PAYMENT_STATUS_NULL = '';
  const PAYMENT_STATUS_INITIALIZED = 'initialized';
  const PAYMENT_STATUS_CANCELED = 'canceled';
  const PAYMENT_STATUS_COMPLETED = 'completed';
  const PAYMENT_STATUS_EXCEPTION = 'exception';

  /**
   * Complete the submission and order.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function completedSubmission(WebformInterface $webform) {
    $webform_submission = $this->getWebformSubmissionFromToken($webform);
    $order = $this->getOrder();

    $this->checkAccess($webform_submission, $order);

    self::setSubmissionOrderStatus($webform_submission, self::PAYMENT_STATUS_COMPLETED);

    // Disable the webform draft state, to mark the payment as completed.
    // Set the webform 'completed' state, to trigger webform handlers such as
    // Exact and Email.
    $webform_submission
      ->set('in_draft', FALSE)
      ->set('completed', TRUE)
      ->save();

    // Transition the order from 'draft' to 'validation'.
    $this->placeOrder($order);

    // Load confirmation page settings.
    $confirmation_type = $webform_submission->getWebform()->getSetting('confirmation_type');
    $has_confirmation_url = in_array($confirmation_type, [WebformInterface::CONFIRMATION_URL, WebformInterface::CONFIRMATION_URL_MESSAGE]);
    $has_confirmation_message = !in_array($confirmation_type, [WebformInterface::CONFIRMATION_URL]);

    $redirect_url = $webform_submission->getSourceUrl();
    if ($has_confirmation_url) {
      // @todo Validate url like \Drupal\webform\WebformSubmissionForm::setConfirmation().
      $url = $webform_submission->getWebform()->getSetting('confirmation_url');
      if ($url) {
        $redirect_url = URL::fromUserInput($url);
      }
    }

    if ($has_confirmation_message) {
      $message = $webform_submission->getWebform()->getSetting('confirmation_message');
      $this->messenger()->addStatus(Xss::filter($message));
    }

    return $this->redirectToUrl($redirect_url);
  }

  /**
   * Cancel the submission and notify user.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function canceledSubmission(WebformInterface $webform) {
    $webform_submission = $this->getWebformSubmissionFromToken($webform);

    self::setSubmissionOrderStatus($webform_submission, self::PAYMENT_STATUS_CANCELED);
    $webform_submission->resave();

    $this->messenger()->addWarning(t('The payment has been canceled, please re-submit the form to complete the payment.'));

    return $this->redirectToUrl($webform_submission->getSourceUrl());
  }

  /**
   * Cancel the submission, notify user and log the exception.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function exceptionSubmission(WebformInterface $webform) {
    $webform_submission = $this->getWebformSubmissionFromToken($webform);

    self::setSubmissionOrderStatus($webform_submission, self::PAYMENT_STATUS_EXCEPTION);
    $webform_submission->resave();

    $this->messenger()->addError(t('Something went wrong, the payment has been canceled. Please try again later.'));

    return $this->redirectToUrl($webform_submission->getSourceUrl());
  }

  /**
   * Transition the order status to 'completed'.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A order.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function placeOrder(OrderInterface $order) {
    $transition = $order->getState()->getWorkflow()->getTransition('place');
    $order->getState()->applyTransition($transition);

    // The order is probably payed, allow editing by shop managers again.
    $order->unlock();

    $order->save();
  }

  /**
   * Get the order from the current request.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   A order.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getOrder() {
    $order_id = \Drupal::requestStack()->getCurrentRequest()->get('order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->load($order_id);
    return $order;
  }

  /**
   * Get webform submission from query token.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform, related to the token.
   *
   * @return \Drupal\webform\WebformSubmissionInterface|null
   *   A submission loaded from the token.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getWebformSubmissionFromToken(WebformInterface $webform) {
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');

    $token = \Drupal::requestStack()->getCurrentRequest()->get('submission');
    if (!$token) {
      throw new AccessDeniedHttpException('Token not found.');
    }

    $webform_submission = $storage->loadFromToken($token, $webform);
    if (!$webform_submission) {
      throw new AccessDeniedHttpException('Webform submission failed to load.');
    }

    return $webform_submission;
  }

  /**
   * Check if the submission and order.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A order.
   */
  protected function checkAccess(WebformSubmissionInterface $webform_submission, OrderInterface $order) {
    if (!$order || !$webform_submission->isDraft() || $order->getState()->value == 'completed') {
      throw new AccessDeniedHttpException('Submission already completed.');
    }
  }

  /**
   * Redirect to the given Url.
   *
   * @param \Drupal\Core\URL $url
   *   Url to redirect to.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The Redirect response.
   */
  protected function redirectToUrl(URL $url) {
    return $this->redirect($url->getRouteName(), $url->getRouteParameters());
  }

  /**
   * Set the Order status in the Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform Submission.
   * @param string $status
   *   The status to store in the Submission.
   */
  public static function setSubmissionOrderStatus(WebformSubmissionInterface $webformSubmission, $status) {
    $handlers = $webformSubmission->getWebform()->getHandlers('webform_product');

    $config = $handlers->getConfiguration();
    /** @var \Drupal\webform\Plugin\WebformHandlerInterface $handler */
    $handler = reset($config);
    $settings = $handler['settings'];

    if ($settings[WebformProductWebformHandler::FIELD_STATUS]) {
      $webformSubmission->setElementData($settings[WebformProductWebformHandler::FIELD_STATUS], $status);
    }
  }

}
