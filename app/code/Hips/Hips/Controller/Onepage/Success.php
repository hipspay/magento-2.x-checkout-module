<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 06/09/17
 * Time: 4:28 PM
 */

namespace Hips\Hips\Controller\Onepage;


use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;

class Success extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    protected $cartHelper;
    /**
     * @var \Hips\Hips\Model\Fullpay\CheckoutFactory
     */
    protected $checkoutFactory;

    /**
     * Success constructor.
     * @param Context $context
     * @param \Magento\Checkout\Helper\Cart $cartHelper
     * @param \Hips\Hips\Model\Fullpay\CheckoutFactory $checkoutFactory
     */
    public function __construct(Context $context,
                                \Magento\Checkout\Helper\Cart $cartHelper,
                                \Hips\Hips\Model\Fullpay\CheckoutFactory $checkoutFactory
                                )
    {
        parent::__construct($context);
        $this->cartHelper = $cartHelper;
        $this->checkoutFactory = $checkoutFactory;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $checkout = $this->checkoutFactory->create();
        $checkout->placeOrder();
        $this->_redirect('checkout/onepage/success');
    }
}