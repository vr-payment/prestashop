<?php
/**
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

use VRPayment\Sdk\Service\TransactionInvoiceService;
use VRPayment\Sdk\Service\TransactionLineItemVersionService;
use VRPayment\Sdk\Model\TransactionLineItemVersionCreate;
use VRPayment\Sdk\Model\AbstractTransactionPending;

/**
 * This service provides functions to deal with VR Payment transactions.
 */
class VRPaymentServiceTransaction extends VRPaymentServiceAbstract
{
    private const CART_HASH_META_KEY = 'transactionCartHash';
    private const POSSIBLE_PAYMENT_METHOD_CACHE_KEY = 'possiblePaymentMethods';
    private const POSSIBLE_PAYMENT_METHOD_CACHE_TTL = 120;
    private const TRANSACTION_CACHE_META_KEY = 'cachedTransaction';
    private const TRANSACTION_CACHE_TTL = 60;
    private const POSSIBLE_PAYMENT_METHOD_SESSION_KEY = 'vrpPossiblePaymentMethods';
    private const JS_URL_CACHE_META_KEY = 'cachedJsUrl';
    private const JS_URL_CACHE_TTL = 300;
    /**
     * Cache for cart transactions.
     *
     * @var \VRPayment\Sdk\Model\Transaction[]
     */
    private static $transactionCache = array();

    /**
     * Cache for possible payment methods by cart.
     *
     * @var \VRPayment\Sdk\Model\PaymentMethodConfiguration[]
     */
    private static $possiblePaymentMethodCache = array();

    /**
     * The transaction API service.
     *
     * @var \VRPayment\Sdk\Service\TransactionService
     */
    private $transactionService;

    /**
     * The transaction iframe API service to retrieve js url.
     *
     * @var \VRPayment\Sdk\Service\TransactionIframeService
     */
    private $transactionIframeService;

    /**
     * The transaction payment page API service to retrieve redirection url.
     *
     * @var \VRPayment\Sdk\Service\TransactionPaymentPageService
     */
    private $transactionPaymentPageService;

    /**
     * The charge attempt API service.
     *
     * @var \VRPayment\Sdk\Service\ChargeAttemptService
     */
    private $chargeAttemptService;

    /**
     * Line item version service.
     *
     * @var \VRPayment\Sdk\Service\TransactionLineItemVersionService
     */
    private $transactionLineItemVersionService;

    /**
     * Per-request cache for loaded transactions by space/transaction id.
     *
     * @var \VRPayment\Sdk\Model\Transaction[]
     */
    private $loadedTransactions = array();

    /**
     * Per-request cache for successful charge attempts.
     *
     * @var \VRPayment\Sdk\Model\ChargeAttempt[]
     */
    private $chargeAttemptCache = array();

    /**
     * Per-request cache for customers.
     *
     * @var Customer[]
     */
    private $customerCache = array();

    /**
     * Per-request cache for countries.
     *
     * @var Country[]
     */
    private $countryCache = array();

    /**
     * Per-request cache for states.
     *
     * @var State[]
     */
    private $stateCache = array();

    /**
     * Per-request cache for carriers.
     *
     * @var Carrier[]
     */
    private $carrierCache = array();

    /**
     * Small helper to lazily create SDK services with a shared API client.
     *
     * @param mixed  $property
     * @param string $className
     * @return mixed
     */
    private function getSdkService(&$property, $className)
    {
        if ($property === null) {
            $property = new $className(VRPaymentHelper::getApiClient());
        }
        return $property;
    }

    /**
     * Returns the transaction API service.
     *
     * @return \VRPayment\Sdk\Service\TransactionService
     */
    protected function getTransactionService()
    {
        return $this->getSdkService(
            $this->transactionService,
            \VRPayment\Sdk\Service\TransactionService::class
        );
    }

    /**
     * Returns the transaction iframe API service.
     *
     * @return \VRPayment\Sdk\Service\TransactionIframeService
     */
    protected function getTransactionIframeService()
    {
        return $this->getSdkService(
            $this->transactionIframeService,
            \VRPayment\Sdk\Service\TransactionIframeService::class
        );
    }

    /**
     * Returns the transaction API payment page service.
     *
     * @return \VRPayment\Sdk\Service\TransactionPaymentPageService
     */
    protected function getTransactionPaymentPageService()
    {
        return $this->getSdkService(
            $this->transactionPaymentPageService,
            \VRPayment\Sdk\Service\TransactionPaymentPageService::class
        );
    }

    /**
     * Returns the charge attempt API service.
     *
     * @return \VRPayment\Sdk\Service\ChargeAttemptService
     */
    protected function getChargeAttemptService()
    {
        return $this->getSdkService(
            $this->chargeAttemptService,
            \VRPayment\Sdk\Service\ChargeAttemptService::class
        );
    }

    /**
     * Wait for the transaction to be in one of the given states.
     *
     * @param Order $order
     * @param array $states
     * @param int   $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Order $order, array $states, $maxWaitTime = 5): bool
    {
        $start = microtime(true);

        do {
            $transactionInfo = VRPaymentModelTransactioninfo::loadByOrderId($order->id);

            if ($transactionInfo && in_array($transactionInfo->getState(), $states, true)) {
                return true;
            }

            usleep(150000);
        } while (microtime(true) - $start < $maxWaitTime);

        return false;
    }

    /**
     * Returns the URL to VR Payment's JavaScript library that is necessary to display the payment form.
     *
     * @param Cart $cart
     * @return string
     */
    public function getJavascriptUrl(Cart $cart)
    {
        $transaction = $this->getTransactionFromCart($cart);
        $cachedUrl = $this->getCachedJavascriptUrl($cart, $transaction);
        if ($cachedUrl !== null) {
            return $cachedUrl;
        }

        $js = $this->getTransactionIframeService()->javascriptUrl(
            $transaction->getLinkedSpaceId(),
            $transaction->getId()
        );

        $url = $js . '&className=vrpaymentIFrameCheckoutHandler';
        $this->storeCachedJavascriptUrl($cart, $transaction, $url);

        return $url;
    }

    /**
     * Returns the URL to VR Payment's payment page.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return string
     */
    public function getPaymentPageUrl($spaceId, $transactionId)
    {
        return $this->getTransactionPaymentPageService()->paymentPageUrl($spaceId, $transactionId);
    }

    /**
     * Returns the transaction with the given id (cached per request).
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \VRPayment\Sdk\Model\Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        $key = $spaceId . '-' . $transactionId;
        if (!isset($this->loadedTransactions[$key])) {
            $this->loadedTransactions[$key] = $this->getTransactionService()->read($spaceId, $transactionId);
        }
        return $this->loadedTransactions[$key];
    }

    /**
     * Returns the last failed charge attempt of the transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \VRPayment\Sdk\Model\ChargeAttempt|null
     */
    public function getFailedChargeAttempt($spaceId, $transactionId)
    {
        $chargeAttemptService = $this->getChargeAttemptService();
        $query = new \VRPayment\Sdk\Model\EntityQuery();
        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
            $this->createEntityFilter('charge.transaction.id', $transactionId),
            $this->createEntityFilter('state', \VRPayment\Sdk\Model\ChargeAttemptState::FAILED),
            )
        );
        $query->setFilter($filter);
        $query->setOrderBys(
            array(
            $this->createEntityOrderBy('failedOn'),
            )
        );
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($spaceId, $query);
        if ($result != null && !empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Create a version of line items
     *
     * @param string                                                    $spaceId
     * @param TransactionLineItemVersionCreate $lineItemVersion
     * @return \VRPayment\Sdk\Model\TransactionLineItemVersion
     * @throws \VRPayment\Sdk\ApiException
     * @throws \VRPayment\Sdk\Http\ConnectionException
     * @throws \VRPayment\Sdk\VersioningException
     */
    public function updateLineItems($spaceId, TransactionLineItemVersionCreate $lineItemVersion)
    {
        return $this->getTransactionLineItemVersionService()->create($spaceId, $lineItemVersion);
    }

    /**
     * Stores the transaction data in the database.
     *
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @param Order                                        $order
     * @return VRPaymentModelTransactioninfo
     */
    public function updateTransactionInfo(\VRPayment\Sdk\Model\Transaction $transaction, Order $order)
    {
        $info = VRPaymentModelTransactioninfo::loadByTransaction(
            $transaction->getLinkedSpaceId(),
            $transaction->getId()
        );
        $info->setTransactionId($transaction->getId());
        $info->setAuthorizationAmount($transaction->getAuthorizationAmount());
        $info->setOrderId($order->id);
        $info->setState($transaction->getState());
        $info->setSpaceId($transaction->getLinkedSpaceId());
        $info->setSpaceViewId($transaction->getSpaceViewId());
        $info->setLanguage($transaction->getLanguage());
        $info->setCurrency($transaction->getCurrency());
        $info->setConnectorId(
            $transaction->getPaymentConnectorConfiguration() != null
            ? $transaction->getPaymentConnectorConfiguration()->getConnector()
            : null
        );
        $info->setPaymentMethodId(
            $transaction->getPaymentConnectorConfiguration() != null
            && $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null
            ? $transaction->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration()
            ->getPaymentMethod()
            : null
        );

        // Avoid calling getPaymentMethodImage() twice.
        $paymentMethodImage = $this->getPaymentMethodImage($transaction, $order);
        $info->setImage($this->getResourcePath($paymentMethodImage));
        $info->setImageBase($this->getResourceBase($paymentMethodImage));

        $info->setLabels($this->getTransactionLabels($transaction));
        if (
            $transaction->getState() == \VRPayment\Sdk\Model\TransactionState::FAILED
            || $transaction->getState() == \VRPayment\Sdk\Model\TransactionState::DECLINE
        ) {
            $failedChargeAttempt = $this->getFailedChargeAttempt(
                $transaction->getLinkedSpaceId(),
                $transaction->getId()
            );
            if ($failedChargeAttempt != null && $failedChargeAttempt->getFailureReason() != null) {
                $info->setFailureReason(
                    $failedChargeAttempt->getFailureReason()->getDescription()
                );
            } elseif ($transaction->getFailureReason() != null) {
                $info->setFailureReason(
                    $transaction->getFailureReason()->getDescription()
                );
            }
            $info->setUserFailureMessage($transaction->getUserFailureMessage());
        }
        $info->save();
        return $info;
    }

    /**
     * Returns an array of the transaction's labels.
     *
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @return string[]
     */
    protected function getTransactionLabels(\VRPayment\Sdk\Model\Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }
            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Returns the successful charge attempt of the transaction (cached per request).
     *
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @return \VRPayment\Sdk\Model\ChargeAttempt|null
     */
    protected function getChargeAttempt(\VRPayment\Sdk\Model\Transaction $transaction)
    {
        $spaceId       = $transaction->getLinkedSpaceId();
        $transactionId = $transaction->getId();
        $key           = $spaceId . '-' . $transactionId;

        if (!isset($this->chargeAttemptCache[$key])) {
            $chargeAttemptService = $this->getChargeAttemptService();
            $query = new \VRPayment\Sdk\Model\EntityQuery();
            $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
            $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
            $filter->setChildren(
                array(
                $this->createEntityFilter('charge.transaction.id', $transactionId),
                $this->createEntityFilter(
                    'state',
                    \VRPayment\Sdk\Model\ChargeAttemptState::SUCCESSFUL
                ),
                )
            );
            $query->setFilter($filter);
            $query->setNumberOfEntities(1);
            $result = $chargeAttemptService->search($spaceId, $query);
            $this->chargeAttemptCache[$key] = ($result != null && !empty($result)) ? current($result) : null;
        }

        return $this->chargeAttemptCache[$key];
    }

    /**
     * Returns the payment method's image.
     *
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @param Order                                        $order
     * @return string|null
     */
    protected function getPaymentMethodImage(\VRPayment\Sdk\Model\Transaction $transaction, Order $order)
    {
        if ($transaction->getPaymentConnectorConfiguration() == null) {
            $moduleName = $order->module;
            if ($moduleName == 'vrpayment') {
                $id = VRPaymentHelper::getOrderMeta($order, 'vRPaymentMethodId');
                $methodConfiguration = new VRPaymentModelMethodconfiguration($id);
                return VRPaymentHelper::getResourceUrl(
                    $methodConfiguration->getImageBase(),
                    $methodConfiguration->getImage()
                );
            }
            return null;
        }
        if ($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null) {
            return $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getResolvedImageUrl();
        }
        return null;
    }

    /**
     * Returns the payment methods that can be used with the current cart.
     *
     * @param Cart $cart
     * @return \VRPayment\Sdk\Model\PaymentMethodConfiguration[]
     * @throws \VRPayment\Sdk\ApiException
     * @throws VRPaymentExceptionInvalidtransactionamount
     */
    public function getPossiblePaymentMethods(
        Cart $cart,
        \VRPayment\Sdk\Model\Transaction $transaction = null
    ) {
        return $this->warmPossiblePaymentMethodCache($cart, $transaction);
    }

    /**
     * Loads the cached payment methods for the cart or refreshes them from the API when required.
     *
     * @param Cart $cart
     * @param \VRPayment\Sdk\Model\Transaction|null $transaction
     * @param bool $forceReload
     * @param bool $failSilently
     * @return \VRPayment\Sdk\Model\PaymentMethodConfiguration[]
     * @throws \VRPayment\Sdk\ApiException
     * @throws VRPaymentExceptionInvalidtransactionamount
     */
    private function warmPossiblePaymentMethodCache(
        Cart $cart,
        \VRPayment\Sdk\Model\Transaction $transaction = null,
        $forceReload = false,
        $failSilently = false
    ) {
        $currentCartId = $cart->id;
        $cartHash = VRPaymentHelper::calculateCartHash($cart);

        $sessionEntry = $this->getSessionPaymentMethodCacheEntry($currentCartId);
        $sessionIsValid = $this->isPaymentMethodCacheEntryValid($sessionEntry, $cartHash);
        $staleSessionMethods = null;
        if ($sessionIsValid && !isset(self::$possiblePaymentMethodCache[$currentCartId])) {
            self::$possiblePaymentMethodCache[$currentCartId] = $this->hydrateCachedPaymentMethods(
                $sessionEntry['methods']
            );
        }

        if (!$sessionIsValid && $sessionEntry !== null) {
            $staleSessionMethods = $this->hydrateCachedPaymentMethods($sessionEntry['methods']);
            $this->clearSessionPaymentMethodCacheEntry($currentCartId);
        }

        $cached = VRPaymentHelper::getCartMeta($cart, self::POSSIBLE_PAYMENT_METHOD_CACHE_KEY);
        $metaIsValid = $this->isPaymentMethodCacheEntryValid($cached, $cartHash);
        if ($metaIsValid && !isset(self::$possiblePaymentMethodCache[$currentCartId])) {
            self::$possiblePaymentMethodCache[$currentCartId] = $cached['methods'];
        }

        if (($sessionIsValid || $metaIsValid) && !$forceReload) {
            return self::$possiblePaymentMethodCache[$currentCartId];
        }

        $staleMetaMethods = (!$metaIsValid && is_array($cached) && isset($cached['methods']))
            ? $cached['methods']
            : null;
        $fallbackMethods = $staleSessionMethods ? $staleSessionMethods : $staleMetaMethods;
        if (is_array($fallbackMethods) && !$staleSessionMethods) {
            $fallbackMethods = $this->hydrateCachedPaymentMethods($fallbackMethods);
        }

        $transaction = $transaction ?: $this->getTransactionFromCart($cart);

        try {
            $paymentMethods = $this->getTransactionService()->fetchPaymentMethods(
                $transaction->getLinkedSpaceId(),
                $transaction->getId(),
                'iframe'
            );
        } catch (\VRPayment\Sdk\ApiException $e) {
            if (!empty($fallbackMethods)) {
                self::$possiblePaymentMethodCache[$currentCartId] = $fallbackMethods;
                $this->persistPaymentMethodCache($cart, $cartHash, $fallbackMethods);
                return $fallbackMethods;
            }
            self::$possiblePaymentMethodCache[$currentCartId] = array();
            VRPaymentHelper::clearCartMeta($cart, self::POSSIBLE_PAYMENT_METHOD_CACHE_KEY);
            $this->clearSessionPaymentMethodCacheEntry($currentCartId);
            if ($failSilently) {
                return array();
            }
            throw $e;
        } catch (VRPaymentExceptionInvalidtransactionamount $e) {
            if (!empty($fallbackMethods)) {
                self::$possiblePaymentMethodCache[$currentCartId] = $fallbackMethods;
                $this->persistPaymentMethodCache($cart, $cartHash, $fallbackMethods);
                return $fallbackMethods;
            }
            self::$possiblePaymentMethodCache[$currentCartId] = array();
            VRPaymentHelper::clearCartMeta($cart, self::POSSIBLE_PAYMENT_METHOD_CACHE_KEY);
            $this->clearSessionPaymentMethodCacheEntry($currentCartId);
            if ($failSilently) {
                return array();
            }
            throw $e;
        }

        if (empty($paymentMethods) && !empty($fallbackMethods)) {
            $paymentMethods = $fallbackMethods;
        }

        self::$possiblePaymentMethodCache[$currentCartId] = $paymentMethods;
        $this->persistPaymentMethodCache($cart, $cartHash, $paymentMethods);

        return self::$possiblePaymentMethodCache[$currentCartId];
    }

    /**
     * Determines if the cached entry is valid for the current cart hash.
     *
     * @param mixed $cachedEntry
     * @param string $cartHash
     * @return bool
     */
    private function isPaymentMethodCacheEntryValid($cachedEntry, $cartHash)
    {
        return is_array($cachedEntry)
            && isset($cachedEntry['hash'], $cachedEntry['methods'], $cachedEntry['expires'])
            && $cachedEntry['hash'] === $cartHash
            && $cachedEntry['expires'] >= time();
    }

    /**
     * Retrieves a cached session entry for the given cart id.
     *
     * @param int $cartId
     * @return array|null
     */
    private function getSessionPaymentMethodCacheEntry($cartId)
    {
        $data = $this->getSessionPaymentMethodCacheData();
        return isset($data[$cartId]) ? $data[$cartId] : null;
    }

    /**
     * Stores the given payment methods in the session cache for the current cart.
     *
     * @param int $cartId
     * @param string $cartHash
     * @param array $paymentMethods
     * @return void
     */
    private function storeSessionPaymentMethodCacheEntry($cartId, $cartHash, array $paymentMethods)
    {
        $data = array(
            $cartId => array(
            'hash' => $cartHash,
            'expires' => time() + self::POSSIBLE_PAYMENT_METHOD_CACHE_TTL,
            'methods' => $this->convertPaymentMethodsForSession($paymentMethods)
            )
        );
        $this->persistSessionPaymentMethodCacheData($data);
    }

    /**
     * Persists the payment method cache across cart meta and session storage.
     *
     * @param Cart $cart
     * @param string $cartHash
     * @param array $paymentMethods
     * @return void
     */
    private function persistPaymentMethodCache(Cart $cart, $cartHash, array $paymentMethods)
    {
        VRPaymentHelper::updateCartMeta(
            $cart,
            self::POSSIBLE_PAYMENT_METHOD_CACHE_KEY,
            array(
                'hash' => $cartHash,
                'expires' => time() + self::POSSIBLE_PAYMENT_METHOD_CACHE_TTL,
                'methods' => $paymentMethods
            )
        );
        $this->storeSessionPaymentMethodCacheEntry($cart->id, $cartHash, $paymentMethods);
    }

    /**
     * Removes a session cache entry for the given cart id.
     *
     * @param int $cartId
     * @return void
     */
    private function clearSessionPaymentMethodCacheEntry($cartId)
    {
        $data = $this->getSessionPaymentMethodCacheData();
        if (isset($data[$cartId])) {
            unset($data[$cartId]);
            $this->persistSessionPaymentMethodCacheData($data);
        }
    }

    /**
     * Returns the raw cache map stored in the session.
     *
     * @return array
     */
    private function getSessionPaymentMethodCacheData()
    {
        $context = Context::getContext();
        if (!isset($context->cookie)) {
            return array();
        }

        $cookie = $context->cookie;
        $key = self::POSSIBLE_PAYMENT_METHOD_SESSION_KEY;
        if (!isset($cookie->$key) || empty($cookie->$key)) {
            return array();
        }

        $decoded = VRPaymentTools::base64Decode($cookie->$key);
        $data = @unserialize($decoded);

        return is_array($data) ? $data : array();
    }

    /**
     * Persists the payment method cache map back into the session.
     *
     * @param array $data
     * @return void
     */
    private function persistSessionPaymentMethodCacheData(array $data)
    {
        $context = Context::getContext();
        if (!isset($context->cookie)) {
            return;
        }

        $cookie = $context->cookie;
        $key = self::POSSIBLE_PAYMENT_METHOD_SESSION_KEY;
        if (empty($data)) {
            unset($cookie->$key);
        } else {
            $cookie->$key = VRPaymentTools::base64Encode(serialize($data));
        }
        $cookie->write();
    }

    /**
     * Normalizes payment methods before persisting in the session cache.
     *
     * @param array $paymentMethods
     * @return array
     */
    private function convertPaymentMethodsForSession(array $paymentMethods)
    {
        $normalized = array();
        foreach ($paymentMethods as $method) {
            if ($method instanceof \VRPayment\Sdk\Model\PaymentMethodConfiguration) {
                $normalized[] = (int)$method->getSpaceId() . ':' . (int)$method->getId();
            } elseif (is_array($method) && isset($method['spaceId'], $method['id'])) {
                $normalized[] = (int)$method['spaceId'] . ':' . (int)$method['id'];
            }
        }
        return implode('|', $normalized);
    }

    /**
     * Rehydrates cached payment methods from their normalized representation.
     *
     * @param mixed $cachedMethods
     * @return \VRPayment\Sdk\Model\PaymentMethodConfiguration[]
     */
    private function hydrateCachedPaymentMethods($cachedMethods)
    {
        if (is_string($cachedMethods)) {
            $cachedMethods = $this->decodeSessionPaymentMethodsString($cachedMethods);
        }

        if (!is_array($cachedMethods)) {
            return array();
        }

        $result = array();
        foreach ($cachedMethods as $method) {
            if ($method instanceof \VRPayment\Sdk\Model\PaymentMethodConfiguration) {
                $result[] = $method;
                continue;
            }

            if (is_array($method) && isset($method['spaceId'], $method['id'])) {
                $result[] = $this->createPaymentMethodStub($method['spaceId'], $method['id']);
            }
        }

        return $result;
    }

    /**
     * Decodes the compact session stored payment method list.
     *
     * @param string $value
     * @return array
     */
    private function decodeSessionPaymentMethodsString($value)
    {
        if (!is_string($value) || $value === '') {
            return array();
        }
        $result = array();
        foreach (explode('|', $value) as $pair) {
            $parts = explode(':', $pair);
            if (count($parts) !== 2) {
                continue;
            }
            $result[] = array(
                'spaceId' => (int)$parts[0],
                'id' => (int)$parts[1]
            );
        }
        return $result;
    }

    /**
     * Builds a lightweight payment method configuration instance from cache data.
     *
     * @param int $spaceId
     * @param int $configurationId
     * @return \VRPayment\Sdk\Model\PaymentMethodConfiguration
     */
    private function createPaymentMethodStub($spaceId, $configurationId)
    {
        $method = new \VRPayment\Sdk\Model\PaymentMethodConfiguration();
        $method->setSpaceId($spaceId);
        $method->setId($configurationId);

        return $method;
    }

    /**
     * Clears the cached transaction and payment-providers data for the cart.
     *
     * @param Cart $cart
     * @return void
     */
    public function invalidateTransactionCache(Cart $cart)
    {
        $cartId = $cart->id;
        unset(self::$transactionCache[$cartId], self::$possiblePaymentMethodCache[$cartId]);
        $this->clearCachedTransactionForCart($cart);
        $this->clearSessionPaymentMethodCacheEntry($cartId);
        VRPaymentHelper::clearCartMeta($cart, self::CART_HASH_META_KEY);
        VRPaymentHelper::clearCartMeta($cart, self::POSSIBLE_PAYMENT_METHOD_CACHE_KEY);
        $this->clearCachedJavascriptUrl($cart);
    }

    /**
     * Rebuilds the transaction and payment-method caches for the cart.
     *
     * @param Cart $cart
     * @return void
     */
    public function refreshTransactionCache(Cart $cart)
    {
        try {
            $this->clearCachedJavascriptUrl($cart);
            $transaction = $this->getTransactionFromCart($cart);
            $this->warmPossiblePaymentMethodCache($cart, $transaction, true, true);
        } catch (Exception $e) {
            // Silently ignore; cache refresh is best-effort.
        }
    }

    /**
     * Returns a cached transaction for the given cart if available and valid.
     *
     * @param Cart $cart
     * @param int $spaceId
     * @param int $transactionId
     * @return \VRPayment\Sdk\Model\Transaction|null
     */
    private function getCachedTransactionForCart(Cart $cart, $spaceId, $transactionId)
    {
        $cached = VRPaymentHelper::getCartMeta($cart, self::TRANSACTION_CACHE_META_KEY);
        if (!is_array($cached)
            || !isset($cached['spaceId'], $cached['transactionId'], $cached['expires'], $cached['data'])
            || (int)$cached['spaceId'] !== (int)$spaceId
            || (int)$cached['transactionId'] !== (int)$transactionId
        ) {
            return null;
        }

        if ($cached['expires'] < time()) {
            $this->clearCachedTransactionForCart($cart);
            return null;
        }

        $serialized = VRPaymentTools::base64Decode($cached['data']);
        $transaction = @unserialize($serialized);
        if ($transaction instanceof \VRPayment\Sdk\Model\Transaction) {
            $this->cacheLoadedTransactionObject($transaction);
            return $transaction;
        }

        $this->clearCachedTransactionForCart($cart);
        return null;
    }

    /**
     * Stores the transaction information for reuse if still pending.
     *
     * @param Cart $cart
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @return void
     */
    private function storeCachedTransactionForCart(Cart $cart, \VRPayment\Sdk\Model\Transaction $transaction)
    {
        if ($transaction->getState() != \VRPayment\Sdk\Model\TransactionState::PENDING) {
            $this->clearCachedTransactionForCart($cart);
            return;
        }

        $this->cacheLoadedTransactionObject($transaction);
        VRPaymentHelper::updateCartMeta(
            $cart,
            self::TRANSACTION_CACHE_META_KEY,
            array(
                'spaceId' => $transaction->getLinkedSpaceId(),
                'transactionId' => $transaction->getId(),
                'expires' => time() + self::TRANSACTION_CACHE_TTL,
                'data' => VRPaymentTools::base64Encode(serialize($transaction))
            )
        );
    }

    /**
     * Clears the cached transaction data for the cart.
     *
     * @param Cart $cart
     * @return void
     */
    private function clearCachedTransactionForCart(Cart $cart)
    {
        VRPaymentHelper::clearCartMeta($cart, self::TRANSACTION_CACHE_META_KEY);
    }

    /**
     * Stores the given transaction in the in-memory id cache.
     *
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @return void
     */
    private function cacheLoadedTransactionObject(\VRPayment\Sdk\Model\Transaction $transaction)
    {
        if ($transaction == null) {
            return;
        }
        $key = $transaction->getLinkedSpaceId() . '-' . $transaction->getId();
        $this->loadedTransactions[$key] = $transaction;
    }

    /**
     * Returns the cached iframe javascript URL if it matches the current transaction.
     *
     * @param Cart $cart
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @return string|null
     */
    private function getCachedJavascriptUrl(Cart $cart, \VRPayment\Sdk\Model\Transaction $transaction)
    {
        $cached = VRPaymentHelper::getCartMeta($cart, self::JS_URL_CACHE_META_KEY);
        if (!is_array($cached)
            || !isset($cached['spaceId'], $cached['transactionId'], $cached['expires'], $cached['url'])
        ) {
            return null;
        }

        if ((int)$cached['spaceId'] !== (int)$transaction->getLinkedSpaceId()
            || (int)$cached['transactionId'] !== (int)$transaction->getId()
        ) {
            $this->clearCachedJavascriptUrl($cart);
            return null;
        }

        if ($cached['expires'] < time()) {
            $this->clearCachedJavascriptUrl($cart);
            return null;
        }

        return $cached['url'];
    }

    /**
     * Stores the iframe javascript URL for the transaction.
     *
     * @param Cart $cart
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @param string $url
     * @return void
     */
    private function storeCachedJavascriptUrl(
        Cart $cart,
        \VRPayment\Sdk\Model\Transaction $transaction,
        $url
    ) {
        VRPaymentHelper::updateCartMeta(
            $cart,
            self::JS_URL_CACHE_META_KEY,
            array(
                'spaceId' => $transaction->getLinkedSpaceId(),
                'transactionId' => $transaction->getId(),
                'expires' => time() + self::JS_URL_CACHE_TTL,
                'url' => $url
            )
        );
    }

    /**
     * Clears the cached javascript URL for the cart.
     *
     * @param Cart $cart
     * @return void
     */
    private function clearCachedJavascriptUrl(Cart $cart)
    {
        VRPaymentHelper::clearCartMeta($cart, self::JS_URL_CACHE_META_KEY);
    }

    /**
     * Returns the previously stored cart hash for the transaction.
     *
     * @param Cart $cart
     * @return string|null
     */
    private function getStoredTransactionCartHash(Cart $cart)
    {
        $hash = VRPaymentHelper::getCartMeta($cart, self::CART_HASH_META_KEY);
        return is_string($hash) ? $hash : null;
    }

    /**
     * Persists the cart hash associated with the transaction.
     *
     * @param Cart $cart
     * @param string $cartHash
     * @return void
     */
    private function storeTransactionCartHash(Cart $cart, $cartHash)
    {
        VRPaymentHelper::updateCartMeta($cart, self::CART_HASH_META_KEY, $cartHash);
    }

    /**
     * Checks if the transaction for the cart is still pending.
     *
     * @param Cart $cart
     * @throws Exception
     */
    public function checkTransactionPending(Cart $cart)
    {
        $ids = VRPaymentHelper::getCartMeta($cart, 'mappingIds');
        $transaction = $this->getTransaction($ids['spaceId'], $ids['transactionId']);
        if ($transaction->getState() !== \VRPayment\Sdk\Model\TransactionState::PENDING) {
            $newTransaction = $this->createTransactionFromCart($cart);
			PrestaShopLogger::addLog('Expired transaction: ' . $transaction->getId() . ' and created new transaction: ' . $newTransaction->getId());
        }
    }

    /**
     * Update the transaction with the given orders data.
     * The $dataSource is for the address and id information for the transaction.
     * The $orders are use to compile all lineItems, this array needs to include the $dataSource order
     *
     * @param Order $dataSource
     * @param Order[] $orders
     * @param int   $methodConfigurationId
     * @return \VRPayment\Sdk\Model\Transaction
     * @throws Exception
     */
    public function confirmTransaction(Order $dataSource, array $orders, $methodConfigurationId)
    {
        $last = new Exception('Unexpected Error');
        for ($i = 0; $i < 5; $i++) {
            try {
                $ids = VRPaymentHelper::getOrderMeta($dataSource, 'mappingIds');
                $spaceId = $ids['spaceId'];
                $transaction = $this->getTransaction($ids['spaceId'], $ids['transactionId']);

                if ($transaction->getState() != \VRPayment\Sdk\Model\TransactionState::PENDING) {
                    throw new Exception(
                        VRPaymentHelper::getModuleInstance()->l(
                        'The checkout expired, please try again.',
                        'transaction'
                        )
                    );
                }
                $pendingTransaction = new \VRPayment\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleOrderTransactionData($dataSource, $orders, $pendingTransaction);
                $pendingTransaction->setAllowedPaymentMethodConfigurations(array($methodConfigurationId));
                $result = $this->getTransactionService()->confirm($spaceId, $pendingTransaction);
                VRPaymentHelper::updateOrderMeta(
                    $dataSource,
                    'mappingIds',
                    array(
                    'spaceId'       => $result->getLinkedSpaceId(),
                    'transactionId' => $result->getId(),
                    )
                );
                return $result;
            } catch (\VRPayment\Sdk\VersioningException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    /**
     * Assemble the transaction data for the given orders.
     * @param Order $dataSource
     * @param array $orders
     * @param AbstractTransactionPending $transaction
     * @return void
     * @throws VRPaymentExceptionInvalidtransactionamount
     */
    protected function assembleOrderTransactionData(
        Order $dataSource,
        array $orders,
        AbstractTransactionPending $transaction
    ) {
        $transaction->setCurrency(VRPaymentHelper::convertCurrencyIdToCode($dataSource->id_currency));
        $transaction->setBillingAddress($this->getAddress($dataSource->id_address_invoice));
        $transaction->setShippingAddress($this->getAddress($dataSource->id_address_delivery));
        $transaction->setCustomerEmailAddress($this->getEmailAddressForCustomerId($dataSource->id_customer));
        $transaction->setCustomerId($dataSource->id_customer);
        $transaction->setLanguage(VRPaymentHelper::convertLanguageIdToIETF($dataSource->id_lang));
        $transaction->setShippingMethod(
            $this->fixLength($this->getShippingMethodNameForCarrierId($dataSource->id_carrier), 200)
        );

        $transaction->setLineItems(VRPaymentServiceLineitem::instance()->getItemsFromOrders($orders));

        $orderComment = $this->getOrderComment($orders);
        if (!empty($orderComment)) {
            $transaction->setMetaData(
                array(
                'orderComment' => $orderComment,
                )
            );
        }

        $transaction->setMerchantReference($dataSource->id);
        $transaction->setInvoiceMerchantReference(
            $this->fixLength($this->removeNonAscii($dataSource->reference), 100)
        );

        $transaction->setSuccessUrl(
            Context::getContext()->link->getModuleLink(
            'vrpayment',
            'return',
            array(
                'order_id'       => $dataSource->id,
                'secret'         => VRPaymentHelper::computeOrderSecret($dataSource),
                'action'         => 'success',
                'utm_nooverride' => '1',
            ),
            true
            )
        );

        $transaction->setFailedUrl(
            Context::getContext()->link->getModuleLink(
            'vrpayment',
            'return',
            array(
                'order_id'       => $dataSource->id,
                'secret'         => VRPaymentHelper::computeOrderSecret($dataSource),
                'action'         => 'failure',
                'utm_nooverride' => '1',
            ),
            true
            )
        );
    }

    /**
     * Returns the transaction for the given cart.
     *
     * If no transaction exists, a new one is created.
     *
     * @param Cart $cart
     * @return \VRPayment\Sdk\Model\Transaction
     */
    public function getTransactionFromCart(Cart $cart)
    {
        $currentCartId = $cart->id;
        $spaceId = Configuration::get(
            VRPaymentBasemodule::CK_SPACE_ID,
            null,
            $cart->id_shop_group,
            $cart->id_shop
        );

        if (!isset(self::$transactionCache[$currentCartId]) || self::$transactionCache[$currentCartId] == null) {
            $ids = VRPaymentHelper::getCartMeta($cart, 'mappingIds');
            if (empty($ids) || !isset($ids['spaceId']) || $ids['spaceId'] != $spaceId) {
                $transaction = $this->createTransactionFromCart($cart);
            } else {
                $transaction = $this->loadAndUpdateTransactionFromCart($cart);
            }
            self::$transactionCache[$currentCartId] = $transaction;
        }
        return self::$transactionCache[$currentCartId];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Cart $cart
     * @return \VRPayment\Sdk\Model\TransactionCreate
     * @throws \VRPaymentExceptionInvalidtransactionamount
     */
    protected function createTransactionFromCart(Cart $cart)
    {
        $spaceId = Configuration::get(
            VRPaymentBasemodule::CK_SPACE_ID,
            null,
            $cart->id_shop_group,
            $cart->id_shop
        );
        $createTransaction = new \VRPayment\Sdk\Model\TransactionCreate();
        $createTransaction->setCustomersPresence(
            \VRPayment\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT
        );
        $createTransaction->setAutoConfirmationEnabled(false);
        $createTransaction->setDeviceSessionIdentifier(Context::getContext()->cookie->vrp_device_id);

        $spaceViewId = Configuration::get(
            VRPaymentBasemodule::CK_SPACE_VIEW_ID,
            null,
            null,
            $cart->id_shop
        );
        if (!empty($spaceViewId)) {
            $createTransaction->setSpaceViewId($spaceViewId);
        }
        $this->assembleCartTransactionData($cart, $createTransaction);
        $transaction = $this->getTransactionService()->create($spaceId, $createTransaction);
        VRPaymentHelper::updateCartMeta(
            $cart,
            'mappingIds',
            array(
            'spaceId'       => $transaction->getLinkedSpaceId(),
            'transactionId' => $transaction->getId(),
            )
        );
        $this->storeTransactionCartHash($cart, VRPaymentHelper::calculateCartHash($cart));
        $this->clearCachedJavascriptUrl($cart);
        $this->storeCachedTransactionForCart($cart, $transaction);
        $this->warmPossiblePaymentMethodCache($cart, $transaction, true, true);
        return $transaction;
    }

    /**
     * Loads the transaction for the given cart and updates it if necessary.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     */
    protected function loadAndUpdateTransactionFromCart(Cart $cart)
    {
        $ids = VRPaymentHelper::getCartMeta($cart, 'mappingIds');

        // Always fetch fresh transaction - no cache.
        $transaction = $this->getTransaction($ids['spaceId'], $ids['transactionId']);
        if ($transaction === null || $transaction->getState() !== \VRPayment\Sdk\Model\TransactionState::PENDING) {
            $transaction = $this->createTransactionFromCart($cart);
        }

        $customerId   = $transaction->getCustomerId();
        $cartCurrency = VRPaymentHelper::convertCurrencyIdToCode($cart->id_currency);
        $cartHash     = VRPaymentHelper::calculateCartHash($cart);
        $storedHash   = $this->getStoredTransactionCartHash($cart);

        // Condition: update is required if any of these change
        $hasDifferentCustomer = !empty($customerId) && $customerId != $cart->id_customer;
        $hasDifferentCurrency = $transaction->getCurrency() !== $cartCurrency;
        $hasDifferentHash     = $storedHash !== null && $storedHash !== $cartHash;

        if ($hasDifferentCustomer || $hasDifferentCurrency || $hasDifferentHash) {

            $this->storeCachedTransactionForCart($cart, $transaction);

            // Return updated transaction
            return $this->updateTransactionFromCart($cart, $transaction, $cartHash);
        }

        return $transaction;
    }

    /**
     * Updates the remote transaction details to match the current cart.
     *
     * @param Cart $cart
     * @param \VRPayment\Sdk\Model\Transaction $transaction
     * @param string|null $cartHash
     * @return \VRPayment\Sdk\Model\Transaction
     * @throws \VRPayment\Sdk\ApiException
     */
    protected function updateTransactionFromCart(
        Cart $cart,
        \VRPayment\Sdk\Model\Transaction $transaction,
        $cartHash = null
    ) {
        $cartHash = $cartHash ?: VRPaymentHelper::calculateCartHash($cart);

        $pendingTransaction = new \VRPayment\Sdk\Model\TransactionPending();
        $pendingTransaction->setId($transaction->getId());
        $pendingTransaction->setVersion($transaction->getVersion() + 1);
        $this->assembleCartTransactionData($cart, $pendingTransaction);

        $updatedTransaction = $this->getTransactionService()->update(
            $transaction->getLinkedSpaceId(),
            $pendingTransaction
        );

        $this->storeTransactionCartHash($cart, $cartHash);
        $this->clearCachedJavascriptUrl($cart);
        $this->storeCachedTransactionForCart($cart, $updatedTransaction);
        $this->warmPossiblePaymentMethodCache($cart, $updatedTransaction, true, true);

        return $updatedTransaction;
    }

    /**
     * Assemble the transaction data for the given quote.
     *
     * @param Cart                                                       $cart
     * @param \VRPayment\Sdk\Model\AbstractTransactionPending $transaction
     *
     * @return \VRPayment\Sdk\Model\AbstractTransactionPending
     * @throws \VRPaymentExceptionInvalidtransactionamount
     */
    protected function assembleCartTransactionData(
        Cart $cart,
        $transaction
    ) {
        $transaction->setCurrency(VRPaymentHelper::convertCurrencyIdToCode($cart->id_currency));
        $transaction->setBillingAddress($this->getAddress($cart->id_address_invoice));
        $transaction->setShippingAddress($this->getAddress($cart->id_address_delivery));
        if ($cart->id_customer != 0) {
            $transaction->setCustomerEmailAddress($this->getEmailAddressForCustomerId($cart->id_customer));
            $transaction->setCustomerId($cart->id_customer);
        }
        $transaction->setLanguage(VRPaymentHelper::convertLanguageIdToIETF($cart->id_lang));
        $transaction->setShippingMethod(
            $this->fixLength($this->getShippingMethodNameForCarrierId($cart->id_carrier), 200)
        );

        $transaction->setLineItems(VRPaymentServiceLineitem::instance()->getItemsFromCart($cart));

        $transaction->setAllowedPaymentMethodConfigurations(array());
        return $transaction;
    }

    /**
     * Returns the billing/shipping address of the current session.
     *
     * @param int $addressId
     * @return \VRPayment\Sdk\Model\AddressCreate
     */
    protected function getAddress($addressId)
    {
        $prestaAddress = new Address($addressId);

        $address = new \VRPayment\Sdk\Model\AddressCreate();
        $address->setCity($this->fixLength($prestaAddress->city, 100));
        $address->setFamilyName($this->fixLength($prestaAddress->lastname, 100));
        $address->setGivenName($this->fixLength($prestaAddress->firstname, 100));
        $address->setOrganizationName($this->fixLength($prestaAddress->company, 100));
        $address->setPhoneNumber($prestaAddress->phone);

        if ($prestaAddress->id_country != null) {
            $country = $this->getCountryFromCache((int)$prestaAddress->id_country);
            if ($country && !empty($country->iso_code)) {
                $address->setCountry($country->iso_code);
            }
        }
        if ($prestaAddress->id_state != null) {
            $state = $this->getStateFromCache((int)$prestaAddress->id_state);
            if ($state && !empty($state->iso_code)) {
                $address->setPostalState($state->iso_code);
            }
        }
        $address->setPostCode($this->fixLength($prestaAddress->postcode, 40));
        $address->setStreet(
            $this->fixLength(trim($prestaAddress->address1 . "\n" . $prestaAddress->address2), 300)
        );
        $address->setEmailAddress($this->getEmailAddressForCustomerId($prestaAddress->id_customer));
        $address->setDateOfBirth($this->getDateOfBirthForCustomerId($prestaAddress->id_customer));
        $address->setGender($this->getGenderForCustomerId($prestaAddress->id_customer));
        return $address;
    }

    /**
     * Returns cached Customer instance (or null).
     *
     * @param int $id
     * @return Customer|null
     */
    private function getCustomerFromCache($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        if (!isset($this->customerCache[$id])) {
            $this->customerCache[$id] = new Customer($id);
        }
        return $this->customerCache[$id];
    }

    /**
     * Returns cached Country instance (or null).
     *
     * @param int $id
     * @return Country|null
     */
    private function getCountryFromCache($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        if (!isset($this->countryCache[$id])) {
            $this->countryCache[$id] = new Country($id);
        }
        return $this->countryCache[$id];
    }

    /**
     * Returns cached State instance (or null).
     *
     * @param int $id
     * @return State|null
     */
    private function getStateFromCache($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        if (!isset($this->stateCache[$id])) {
            $this->stateCache[$id] = new State($id);
        }
        return $this->stateCache[$id];
    }

    /**
     * Returns cached Carrier instance (or null).
     *
     * @param int $id
     * @return Carrier|null
     */
    private function getCarrierFromCache($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        if (!isset($this->carrierCache[$id])) {
            $this->carrierCache[$id] = new Carrier($id);
        }
        return $this->carrierCache[$id];
    }

    /**
     * Returns the current customer's email address.
     *
     * @param int $id
     * @return string|null
     */
    protected function getEmailAddressForCustomerId($id)
    {
        $customer = $this->getCustomerFromCache($id);
        return $customer ? $customer->email : null;
    }

    /**
     * Returns the current customer's date of birth
     *
     * @param int $id
     * @return \DateTime|null
     */
    protected function getDateOfBirthForCustomerId($id)
    {
        $customer = $this->getCustomerFromCache($id);
        if (!$customer) {
            return null;
        }

        if (!empty($customer->birthday)
            && $customer->birthday != '0000-00-00'
            && Validate::isBirthDate($customer->birthday)
        ) {
            return DateTime::createFromFormat('Y-m-d', $customer->birthday);
        }
        return null;
    }

    /**
     * Returns the current customer's gender.
     *
     * @param int $id
     * @return string|null
     */
    protected function getGenderForCustomerId($id)
    {
        $customer = $this->getCustomerFromCache($id);
        if (!$customer) {
            return null;
        }

        $gender = new Gender($customer->id_gender);
        if (!Validate::isLoadedObject($gender)) {
            return null;
        }
        if ($gender->type == '0') {
            return \VRPayment\Sdk\Model\Gender::MALE;
        } elseif ($gender->type == '1') {
            return \VRPayment\Sdk\Model\Gender::FEMALE;
        }
        return null;
    }

    /**
     * @return TransactionLineItemVersionService
     * @throws Exception
     */
    protected function getTransactionLineItemVersionService()
    {
        if (!$this->transactionLineItemVersionService) {
            $this->transactionLineItemVersionService = new TransactionLineItemVersionService(
                VRPaymentHelper::getApiClient()
            );
        }
        return $this->transactionLineItemVersionService;
    }

    /**
     * Returns the shipping name
     *
     * @param int $carrierId
     * @return string
     */
    protected function getShippingMethodNameForCarrierId($carrierId)
    {
        $carrier = $this->getCarrierFromCache($carrierId);
        return $carrier ? $carrier->name : '';
    }

    /**
     * Returns the order comment (combined for all orders).
     *
     * @param Order[] $orders
     * @return string
     */
    private function getOrderComment(array $orders)
    {
        $messages = array();
        foreach ($orders as $order) {
            $messageCollection = new PrestaShopCollection('Message');
            $messageCollection->where('id_order', '=', (int)$order->id);
            foreach ($messageCollection->getResults() as $orderMessage) {
                $messages[] = $orderMessage->message;
            }
        }
        $unique = array_unique($messages);
        $single = implode("\n", $unique);
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', strip_tags($single));
        return $this->fixLength($cleaned, 512);
    }
}
