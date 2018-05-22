<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 30/08/17
 * Time: 4:03 PM
 */

namespace Hips\Hips\Model\Carrier;


use Magento\Quote\Model\Quote\Address\RateRequest;

class Hipsshipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'hips_shipping';
    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;
    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;
    /**
     * @var \Hips\Hips\Model\Fullpay\CheckoutFactory
     */
    private $checkoutFactory;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * Hipsshipping constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Hips\Hips\Model\Fullpay\CheckoutFactory $checkoutFactory
     * @param array $data
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
                                \Psr\Log\LoggerInterface $logger,
                                \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
                                \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
                                \Magento\Checkout\Model\Session $checkoutSession,
                                \Hips\Hips\Model\Fullpay\CheckoutFactory $checkoutFactory,
                                array $data = []
    )
    {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->checkoutFactory = $checkoutFactory;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return \Magento\Framework\DataObject|bool|null
     */
    public function collectRates(RateRequest $request)
    {
        if ($this->_scopeConfig->getValue('payment/fullpay/active') != 1) {
            return false;
        }
        $shippingPrice = 0.00;
        $title = 'Hips Shipping';
        if($token = $this->checkoutSession->getHipsToken())
        {
            $checkout = $this->checkoutFactory->create();
            $path   = 'orders/'.$token;
            $data = array();
            $resData = $checkout->call($path, 'GET',$data);
            if($resData->require_shipping == true){
                $shipping = $resData->shipping;
                $shippingPrice = ($shipping->fee/100);
                $title = $shipping->name;
            }
        }
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier('hips_shipping');
        $method->setCarrierTitle($title);

        $method->setMethod('hips_shipping');
        $method->setMethodTitle($title);

        /*you can fetch shipping price from different sources over some APIs, we used price from config.xml - xml node price*/
        $amount = $shippingPrice;

        $method->setPrice($amount);
        $method->setCost($amount);

        $result->append($method);

        return $result;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['hips_shipping' => ''];
    }
}