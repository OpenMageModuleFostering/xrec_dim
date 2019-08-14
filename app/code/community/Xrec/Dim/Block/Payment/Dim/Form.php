<?php

class Xrec_Dim_Block_Payment_Dim_Form extends Mage_Payment_Block_Form
{

    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('xrec/form/dim.phtml');
    }

}