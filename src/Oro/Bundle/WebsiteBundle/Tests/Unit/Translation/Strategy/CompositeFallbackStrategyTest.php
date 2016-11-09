<?php

namespace Oro\Bundle\WebsiteBundle\Tests\Unit\Translation\Strategy;

use Oro\Bundle\TranslationBundle\Strategy\DefaultTranslationStrategy;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\WebsiteBundle\Translation\Strategy\CompositeFallbackStrategy;

class CompositeFallbackStrategyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FrontendHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $frontendHelper;

    /**
     * @var DefaultTranslationStrategy|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $frontendStrategy;

    /**
     * @var CompositeFallbackStrategy
     */
    protected $strategy;

    protected function setUp()
    {
        $this->frontendHelper = $this->getMockBuilder('Oro\Bundle\FrontendBundle\Request\FrontendHelper')
            ->disableOriginalConstructor()->getMock();

        $this->frontendStrategy = $this
            ->getMockBuilder('Oro\Bundle\TranslationBundle\Strategy\DefaultTranslationStrategy')
            ->disableOriginalConstructor()->getMock();

        $this->strategy = new CompositeFallbackStrategy($this->frontendHelper, $this->frontendStrategy);
    }

    public function testIsApplicable()
    {
        $isApplicable = true;

        $this->frontendHelper->expects($this->once())
            ->method('isFrontendRequest')
            ->willReturn($isApplicable);

        $this->assertSame($isApplicable, $this->strategy->isApplicable());
    }

    public function testGetLocaleFallbacks()
    {
        $locales = [
            'en' => ['en_EN' => ['en_FR' => []]],
            'ru' => ['ru_RU' => []],
        ];

        $this->frontendStrategy->expects($this->once())
            ->method('getLocaleFallbacks')
            ->willReturn($locales);

        $this->assertSame($locales, $this->strategy->getLocaleFallbacks());
    }

    public function testGetName()
    {
        $name = 'strategy_name';
        $this->frontendStrategy->expects($this->once())
            ->method('getName')
            ->willReturn($name);

        $this->assertSame($name, $this->strategy->getName());
    }
}
