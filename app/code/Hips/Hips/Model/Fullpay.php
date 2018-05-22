<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 30/08/17
 * Time: 3:36 PM
 */

namespace Hips\Hips\Model;


class Fullpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'fullpay';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;


    /**
     * Send authorize request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param  float $amount
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $payment->setTransactionId($payment->getAdditionalInformation('fullpay_token'));
        $payment->setIsTransactionClosed(0);
        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
    }

    /**
     * Send capture request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }
        $errorMsg = '';
        if($payment->getAdditionalInformation('payment_type') == 'authorize'){
            $path = 'orders/'.$payment->getAdditionalInformation('fullpay_token').'/fulfill';
            $data = array();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $checkout = $objectManager->create('\Hips\Hips\Model\Fullpay\Checkout');
            $result = $checkout->call($path,'POST',$data);
            if($result->success != true){
                $errorMsg = 'Error Processing the request';
            }
        }
        if($errorMsg) {
            throw new \Magento\Framework\Exception\LocalizedException($errorMsg);
        } else {
            $payment->setTransactionId($payment->getAdditionalInformation('fullpay_token'));
            $payment->setIsTransactionClosed(0);
        }
        return $this;
    }

}