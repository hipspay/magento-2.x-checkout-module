<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 30/08/17
 * Time: 3:39 PM
 */

namespace Hips\Hips\Controller\Onepage;


use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    private $cartHelper;
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var \Hips\Hips\Model\Fullpay\CheckoutFactory
     */
    private $checkoutFactory;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * Index constructor.
     * @param Context $context
     * @param \Magento\Checkout\Helper\Cart $cartHelper
     */
    public function __construct(Context $context,
                                \Magento\Checkout\Helper\Cart $cartHelper,
                                \Magento\Framework\Registry $registry,
                                \Hips\Hips\Model\Fullpay\CheckoutFactory $checkoutFactory,
                                \Magento\Checkout\Model\Session $checkoutSession
                                )
    {
        parent::__construct($context);
        $this->cartHelper = $cartHelper;
        $this->registry = $registry;
        $this->checkoutFactory = $checkoutFactory;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        if($this->cartHelper->getItemsCount() > 0){
            $checkout = $this->checkoutFactory->create();
            $result = $checkout->start();
            $this->registry->register('token', $result->id);
            $this->checkoutSession->setHipsToken($result->id);
        }else{
            $this->_redirect('checkout/cart');
        }
        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }
}