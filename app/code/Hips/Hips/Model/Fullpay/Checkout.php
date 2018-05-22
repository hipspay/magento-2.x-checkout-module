<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 30/08/17
 * Time: 7:41 PM
 */

namespace Hips\Hips\Model\Fullpay;


class Checkout
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $taxData;
    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutData;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $coreUrl;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    private $quoteManagement;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    const PAYMENT_INFO_TRANSPORT_TOKEN    = 'fullpay_token';


    /**
     * Checkout constructor.
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Tax\Helper\Data $taxData
     * @param \Magento\Checkout\Helper\Data $checkoutData
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\UrlInterface $coreUrl
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param array $params
     * @throws \Exception
     */
    public function __construct(
                                \Magento\Checkout\Model\Session $checkoutSession,
                                \Magento\Customer\Model\Session $customerSession,
                                \Magento\Tax\Helper\Data $taxData,
                                \Magento\Checkout\Helper\Data $checkoutData,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Framework\UrlInterface $coreUrl,
                                \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
                                \Magento\Quote\Model\QuoteManagement $quoteManagement,
                                \Psr\Log\LoggerInterface $logger,
                                $params = []
                                )
    {

        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->taxData = $taxData;
        $this->checkoutData = $checkoutData;
        $this->storeManager = $storeManager;
        $this->coreUrl = $coreUrl;
        $this->scopeConfig = $scopeConfig;
        $params['quote'] = $this->checkoutSession->getQuote();
        if (isset($params['quote']) && $params['quote'] instanceof \Magento\Quote\Model\Quote) {
            $this->_quote = $params['quote'];
        } else {
            throw new \Exception('Quote instance is required.');
        }


        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->logger = $logger;
    }

    /**
     * Gets the Weight unit
     * @return string
     */
    public function getWeightUnit()
    {
        $unit = $this->scopeConfig->getValue('general/locale/weight_unit', ScopeInterface::SCOPE_STORE);
        return str_replace("s","",$unit);
    }

    /**
     * Gets the HipsPayments fulfill from the admin config
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->scopeConfig->getValue('payment/fullpay/payment_action');
    }

    /**
     * Gets the HipsPayments secret key from the admin config
     * @return string Secret Key or empty string if not set
     */
    public function getSecretKey()
    {
        return $this->scopeConfig->getValue('payment/fullpay/private_key');
    }


    public function start(){
        $this->_quote->collectTotals();

        if (!$this->_quote->getGrandTotal() && !$this->_quote->hasNominalItems()) {
            throw new \Exception('Hips does not support processing orders with zero amount.');
        }

        $this->_quote->reserveOrderId();
        $this->quoteRepository->save($this->_quote);

        $request = array();

        $request['order_id'] = $this->_quote->getReservedOrderId();
        $request['purchase_currency'] = $this->_quote->getBaseCurrencyCode(); //'SEK'
        if($this->customerSession->isLoggedIn()){
            $customer = $this->customerSession->getCustomer();
            $request['user_session_id'] = $this->generateRandomString(15);
            $request['user_identifier'] = $customer->getName();
        }else{
            $request['user_session_id'] = $this->generateRandomString(15);
            $request['user_identifier'] = $this->generateRandomString(15);
        }

        $request['meta_data_1'] ='' ;
        if($this->getPaymentAction() == 'authorize'){
            $request['fulfill'] = 'false';
        }else{
            $request['fulfill'] = 'true';
        }
        $request['cart'] = $this->getCart() ;
        if (!$this->_quote->isVirtual()) {
            $request['require_shipping'] = 'true';
            $request['express_shipping'] = 'true';
        }else{
            $request['require_shipping'] = 'false';
            $request['express_shipping'] = 'false';
        }
        $request['ecommerce_platform'] = "Magento 2.1.8";
        $request['ecommerce_module'] = "Hips Magento Module 0.1.0";
        $request['checkout_settings'] = array("extended_cart" => 'true');
        $request['hooks'] = array(
            "user_return_url_on_success" => $this->coreUrl->getUrl('hips/onepage/success'),
            "user_return_url_on_fail"=>$this->coreUrl->getUrl('hips/onepage/failure'),
            "terms_url"=> $this->coreUrl->getUrl('terms'),
            "webhook_url"=> $this->coreUrl->getUrl('hips/confirmations/index')
        );
        $result = $this->call('orders', 'POST', $request);
        return $result;
    }

    public function getCart(){
        $cart = array();
        foreach ($this->_quote->getAllVisibleItems() as $item) {

            $cartItem = array();
            if($item->getProduct()->getIsVirtual()){
                $cartItem['type'] = 'digital';
            }else{
                $cartItem['type'] = 'physical';
            }
            $cartItem['sku'] = $item->getProduct()->getSku();
            $cartItem['name'] = $item->getProduct()->getName();
            $cartItem['quantity'] = $item->getQty();
            $cartItem['unit_price'] = $item->getPriceInclTax()*100;
            $cartItem['discount_rate'] = ($item->getDiscountAmount()/$item->getPrice())*100;
            $cartItem['vat_amount'] = ($item->getPriceInclTax() - $item->getPrice())*100;
            $cartItem['weight_unit'] = $this->getWeightUnit();
            $cartItem['weight'] = $item->getProduct()->getWeight();
            $cart['items'][] = $cartItem;
        }
        /*$cart['items'][] = $this->getShippingInfo();*/
        return $cart;
    }

    /**
     * @return array
     */
    public function getShippingInfo(){
        $cartItem = array();
        $cartItem['type'] = 'shipping_fee';
        $cartItem['sku'] = $this->scopeConfig->getValue('payment/fullpay/shipping_title');
        $cartItem['name'] = $this->scopeConfig->getValue('payment/fullpay/shipping_title');
        $cartItem['quantity'] = 1;
        $cartItem['unit_price'] = $this->scopeConfig->getValue('payment/fullpay/price')*100;
        $cartItem['discount_rate'] = 0;
        $cartItem['vat_amount'] = 0;
        return $cartItem;
    }

    public function getToken()
    {
        return $this->checkoutSession->getHipsToken();

    }

    public function placeOrder()
    {

        $path   = 'orders/'.$this->getToken();
        $data = array();
        $request = $this->call($path, 'GET',$data);
        $billing_address = $request->billing_address;
        $this->_quote->setCustomerEmail($billing_address->email);
        $addressData = array(
            'firstname' => $billing_address->given_name,
            'lastname' => $billing_address->family_name,
            'street' => $billing_address->street_address,
            'city' => $billing_address->city,
            'postcode' => $billing_address->postal_code,
            'telephone' => $billing_address->phone_mobile,
            'country_id' => $billing_address->country
        );

        $billingAddress = $this->_quote->getBillingAddress()->addData($addressData);

        if($request->shipping_address->id){
            $shipping_address = $request->shipping_address;
            $addressData = array(
                'firstname' => $shipping_address->given_name,
                'lastname' => $shipping_address->family_name,
                'street' => $shipping_address->street_address,
                'city' => $shipping_address->city,
                'postcode' => $shipping_address->postal_code,
                'telephone' => $shipping_address->phone_mobile?$shipping_address->phone_mobile:$billing_address->phone_mobile,
                'country_id' => $shipping_address->country
            );
        }


        $shippingAddress = $this->_quote->getShippingAddress()->addData($addressData);
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()
            ->setShippingMethod('hips_shipping_hips_shipping');
        $this->_quote->getPayment()->setMethod('fullpay');
        $this->_quote->getPayment()->importData(array('method' => 'fullpay'));
        $this->_quote->getPayment()->setAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_TOKEN, $request->id);
        $this->prepareGuestQuote($billing_address->email);
        $this->_quote->collectTotals();
        $this->quoteRepository->save($this->_quote);
        $orderId = $this->quoteManagement->placeOrder($this->_quote->getId());
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function prepareGuestQuote($email)
    {
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($email)
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        return $this;
    }


    /**
     * Do the API call
     *
     * @param string $methodName
     * @param array $request
     * @return array
     * @throws Mage_Core_Exception
     */
    public function call($path, $method, array $request)
    {
        try {

            $key = $this->getSecretKey();
            $url = 'https://api.hips.com/v1/'.$path;
            $data = json_encode($request);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if($method == 'POST'){
                curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            }
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION ,1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization:'.$key
                )
            );
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
            $result = curl_exec($ch);
            curl_close($ch);
            return json_decode($result);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}