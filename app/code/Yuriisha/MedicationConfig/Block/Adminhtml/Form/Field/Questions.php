<?php
declare(strict_types=1);

namespace Yuriisha\MedicationConfig\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class Questions extends AbstractFieldArray
{
    /**
     * Medication questions construct
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Creates dynamic row columns
     *
     * @return void
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'question_text',
            [
                'label' => __('Question'),
                'class' => 'required-entry input-text admin__control-text'
            ]
        );
        $this->_addAfter = false;
    }
}
