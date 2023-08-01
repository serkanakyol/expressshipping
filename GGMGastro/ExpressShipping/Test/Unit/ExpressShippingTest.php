<?php

/*
 * NOTICE OF LICENSE
 *
 * @category   GGMGastro
 * @package    GGMGastro_ExpressShipping
 * @copyright  Copyright (c) 2023 Serkan Akyol
 */

namespace GGMGastro\ExpressShipping\Test\Unit;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Quote\Model\Quote\Address\RateRequest;
use GGMGastro\ExpressShipping\Model\Carrier\ExpressShipping;

/**
 *
 */
class ExpressShippingTest extends TestCase
{
    /**
     *
     */
    const DEFAULT_MAX_PACKAGE_WEIGHT_LIMIT = 10;
    /**
     *
     */
    const DEFAULT_BASE_PRICE = 10;
    /**
     *
     */
    const DEFAULT_KG_STEP_FOR_BASE_PRICE = 5;
    /**
     *
     */
    const DEFAULT_ADDITIONAL_PRICE = 2;
    /**
     *
     */
    const DEFAULT_KG_STEP_FOR_ADDITIONAL_PRICE = 1;
    /**
     *
     */
    const DEFAULT_HANDLE_TYPE = 'F';
    /**
     *
     */
    const DEFAULT_HANDLE_FEE = 15;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $_scopeConfig;
    /**
     * @var object
     */
    protected $_expressShipping;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->_scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->_scopeConfig->expects(static::any())
            ->method('getValue')
            ->willReturnCallback([$this, 'scopeConfigValues']);

        $this->_expressShipping = (new ObjectManagerHelper($this))->getObject(
            ExpressShipping::class,
            [
                '_scopeConfig' => $this->_scopeConfig
            ]
        );
    }

    /**
     * @param $xmlPath
     *
     * @return int|string|null
     */
    public function scopeConfigValues($xmlPath)
    {
        switch ($xmlPath) {
            case 'carriers/expressshipping/price':
                return self::DEFAULT_BASE_PRICE;
                break;
            case 'carriers/expressshipping/kg_step_for_base_price':
                return self::DEFAULT_KG_STEP_FOR_BASE_PRICE;
            case 'carriers/expressshipping/additional_price':
                return self::DEFAULT_ADDITIONAL_PRICE;
            case 'carriers/expressshipping/kg_step_for_additional_price':
                return self::DEFAULT_KG_STEP_FOR_ADDITIONAL_PRICE;
            case 'carriers/expressshipping/handling_type':
                return self::DEFAULT_HANDLE_TYPE;
            case 'carriers/expressshipping/handling_fee':
                return self::DEFAULT_HANDLE_FEE;
            case 'carriers/expressshipping/max_package_weight':
                return self::DEFAULT_MAX_PACKAGE_WEIGHT_LIMIT;
                break;
        }
        return null;
    }

    /**
     * @param $packageWeight
     *
     * @return \Magento\Quote\Model\Quote\Address\RateRequest
     */
    protected function prepareRequest($packageWeight = 0)
    {
        $request = new RateRequest();
        $request->setPackageWeight($packageWeight);
        return $request;
    }

    /**
     * @return void
     */
    public function testIsExceedWeightLimit()
    {
        $request = $this->prepareRequest(20);
        $result = $this->_expressShipping->checkWeightLimit($request);
        $this->assertEquals(false, $result);
    }

    /**
     * @return void
     */
    public function testGetShippingPrice()
    {
        $request = $this->prepareRequest(10);
        $result = $this->_expressShipping->getShippingPrice($request);
        $this->assertEquals(35, $result);
    }
}
