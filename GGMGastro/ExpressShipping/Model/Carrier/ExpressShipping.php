<?php

/*
 * NOTICE OF LICENSE
 *
 * @category   GGMGastro
 * @package    GGMGastro_ExpressShipping
 * @copyright  Copyright (c) 2023 Serkan Akyol
 */

namespace GGMGastro\ExpressShipping\Model\Carrier;

use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 *
 */
class ExpressShipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'expressshipping';

    /**
     * @var null
     */
    protected $_result = null;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface          $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory  $rateErrorFactory
     * @param \Psr\Log\LoggerInterface                                    $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory                  $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array                                                       $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return false|string
     */
    protected function getWeightLimit()
    {
        return $this->getConfigData('max_package_weight');
    }

    /**
     * @return \Magento\Shipping\Model\Rate\Result|null
     */
    protected function getResult()
    {
        if (empty($this->_result)) {
            $this->_result = $this->_rateResultFactory->create();
        }
        return $this->_result;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     *
     * @return float|int
     */
    public function getShippingPrice(RateRequest $request)
    {
        $basePrice = $this->getConfigData('price');
        $additionalPricePerKg = $this->getConfigData('additional_price');

        $firstStepWeight = $this->getConfigData('kg_step_for_base_price');
        $additionalStepWeight = $this->getConfigData('kg_step_for_additional_price');

        if($request->getPackageWeight() <= $firstStepWeight) {
            $shippingPrice = $basePrice;
        } else {
            $additionalWeight = round(($request->getPackageWeight() - $firstStepWeight) / $additionalStepWeight);
            $shippingPrice = $basePrice + ($additionalWeight * $additionalPricePerKg);
        }

        $shippingPrice = $this->getFinalPriceWithHandlingFee($shippingPrice);

        return $shippingPrice;
    }

    /**
     * @param $shippingPrice
     * @param $request
     *
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    protected function createResultMethod($shippingPrice)
    {
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        return $method;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     *
     * @return $this|\Magento\Quote\Model\Quote\Address\RateResult\Error
     */
    public function checkWeightLimit(RateRequest $request)
    {
        if($request->getPackageWeight() > $this->getWeightLimit())
        {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     *
     * @return false|\Magento\Shipping\Model\Rate\Result|null
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = $this->getResult();

        if (!$this->checkWeightLimit($request)) {

            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage(
                __(
                    '%1 shipping method is not available for the current order', $this->getConfigData('title')
                )
            );

            $this->getResult()->append($error);
            return $result;
        }

        $shippingPrice = $this->getShippingPrice($request);
        $method = $this->createResultMethod($shippingPrice);
        $result->append($method);

        return $result;
    }
}
