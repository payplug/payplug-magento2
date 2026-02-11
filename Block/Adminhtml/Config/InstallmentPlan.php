<?php
/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

namespace Payplug\Payments\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;

class InstallmentPlan extends Fieldset
{
    /**
     * Render details on InstallmentPlan split options
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $extraElements =
            '<div id="payplug_payments_installment_plan_2" style="display: none;">' .
                '<table>' .
                    '<tr>' .
                        '<td>' . __('Receive') . '</td>' .
                        '<td>' . __('50%') . '</td>' .
                        '<td>' . __('of order amount on the first day') . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td></td>' .
                        '<td>' . __('50%') . '</td>' .
                        '<td>' . __('of order amount on D+30') . '</td>' .
                    '</tr>' .
                '</table>' .
            '</div>'
        ;
        $extraElements .=
            '<div id="payplug_payments_installment_plan_3" style="display: none;">' .
                '<table>' .
                    '<tr>' .
                        '<td>' . __('Receive') . '</td>' .
                        '<td>' . __('34%') . '</td>' .
                        '<td>' . __('of order amount on the first day') . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td></td>' .
                        '<td>' . __('33%') . '</td>' .
                        '<td>' . __('of order amount on D+30') . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td></td>' .
                        '<td>' . __('33%') . '</td>' .
                        '<td>' . __('of order amount on D+60') . '</td>' .
                    '</tr>' .
                '</table>' .
            '</div>'
        ;
        $extraElements .=
            '<div id="payplug_payments_installment_plan_4" style="display: none;">' .
                '<table>' .
                    '<tr>' .
                        '<td>' . __('Receive') . '</td>' .
                        '<td>' . __('25%') . '</td>' .
                        '<td>' . __('of order amount on the first day') . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td></td>' .
                        '<td>' . __('25%') . '</td>' .
                        '<td>' . __('of order amount on D+30') . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td></td>' .
                        '<td>' . __('25%') . '</td>' .
                        '<td>' . __('of order amount on D+60') . '</td>' .
                    '</tr>' .
                    '<tr>' .
                        '<td></td>' .
                        '<td>' . __('25%') . '</td>' .
                        '<td>' . __('of order amount on D+90') . '</td>' .
                    '</tr>' .
                '</table>' .
            '</div>'
        ;

        return parent::render($element) . $extraElements;
    }
}
