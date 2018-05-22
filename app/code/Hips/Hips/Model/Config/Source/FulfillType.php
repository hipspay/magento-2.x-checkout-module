<?php
/**
 * Created by PhpStorm.
 * User: Santhosh
 * Date: 30/08/17
 * Time: 5:05 PM
 */

namespace Hips\Hips\Model\Config\Source;


use Magento\Framework\Option\ArrayInterface;

class FulfillType implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'authorize',
                'label' => __('No'),
            ],
            [
                'value' => 'authorize_capture',
                'label' => __('Yes')
            ]
        ];
    }

}