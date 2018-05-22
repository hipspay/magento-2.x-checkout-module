<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 31/08/17
 * Time: 1:07 AM
 */

namespace Hips\Hips\Plugin;


class Link
{

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlInterface;

    /**
     * Link constructor.
     * @param \Magento\Framework\UrlInterface $urlInterface
     */
    public function __construct(\Magento\Framework\UrlInterface $urlInterface)
    {
        $this->urlInterface = $urlInterface;
    }

    public function afterGetCheckoutUrl(){
       return $this->urlInterface->getUrl('hips/onepage');
    }

}