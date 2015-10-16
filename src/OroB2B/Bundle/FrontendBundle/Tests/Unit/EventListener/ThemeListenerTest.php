<?php

namespace OroB2B\Bundle\FrontendBundle\Tests\Unit\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use Oro\Bundle\ThemeBundle\Model\ThemeRegistry;

use OroB2B\Bundle\FrontendBundle\EventListener\ThemeListener;
use OroB2B\Bundle\FrontendBundle\Request\FrontendHelper;

class ThemeListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ThemeRegistry
     */
    protected $themeRegistry;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|FrontendHelper
     */
    protected $helper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|HttpKernelInterface
     */
    protected $kernel;

    protected function setUp()
    {
        $this->helper = $this->getMockBuilder('OroB2B\Bundle\FrontendBundle\Request\FrontendHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $this->themeRegistry = new ThemeRegistry(
            [
                'oro' => [],
                'demo' => [],
            ]
        );

        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
    }

    /**
     * @param boolean $installed
     * @param int $requestType
     * @param boolean $isFrontendRequest
     * @param string $expectedTheme
     *
     * @dataProvider onKernelRequestProvider
     */
    public function testOnKernelRequest(
        $installed,
        $requestType,
        $isFrontendRequest,
        $expectedTheme
    ) {
        $this->themeRegistry->setActiveTheme('oro');

        $request = new Request();
        $event = new GetResponseEvent($this->kernel, $request, $requestType);

        $this->helper->expects($this->any())
            ->method('isFrontendRequest')
            ->with($request)
            ->willReturn($isFrontendRequest);

        $listener = new ThemeListener($this->themeRegistry, $this->helper, $installed);
        $listener->onKernelRequest($event);

        $this->assertEquals($expectedTheme, $this->themeRegistry->getActiveTheme()->getName());
    }

    /**
     * @return array
     */
    public function onKernelRequestProvider()
    {
        return [
            'not installed application' => [
                'installed' => false,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => true,
                'expectedTheme' => 'oro'
            ],
            'not master request' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::SUB_REQUEST,
                'isFrontendRequest' => true,
                'expectedTheme' => 'oro'
            ],
            'frontend' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => true,
                'expectedTheme' => 'demo'
            ],
            'backend' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => false,
                'expectedTheme' => 'oro'
            ],
        ];
    }

    /**
     * @dataProvider onKernelViewProvider
     *
     * @param bool $installed
     * @param string $requestType
     * @param bool $isFrontendRequest
     * @param bool $hasTheme
     * @param bool|string $deletedAnnotation
     */
    public function testOnKernelView($installed, $requestType, $isFrontendRequest, $hasTheme, $deletedAnnotation)
    {
        $this->themeRegistry->setActiveTheme('oro');

        $request = new Request();
        $request->attributes->set('_template', true);
        $request->attributes->set('_layout', true);
        if ($hasTheme) {
            $request->attributes->set('_theme', 'test');
        }
        $event = new GetResponseForControllerResultEvent(
            $this->kernel,
            $request,
            $requestType,
            []
        );

        $this->helper->expects($this->any())
            ->method('isFrontendRequest')
            ->with($request)
            ->willReturn($isFrontendRequest);

        $listener = new ThemeListener($this->themeRegistry, $this->helper, $installed);

        $listener->onKernelView($event);

        if ($deletedAnnotation && $requestType === HttpKernelInterface::MASTER_REQUEST) {
            $this->assertFalse($request->attributes->has($deletedAnnotation));
        }
    }

    /**
     * @return array
     */
    public function onKernelViewProvider()
    {
        return [
            'not installed application' => [
                'installed' => false,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => false,
                'hasTheme' => false,
                'deletedAnnotation' => false
            ],
            'backend sub-request' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::SUB_REQUEST,
                'isFrontendRequest' => false,
                'hasTheme' => false,
                'deletedAnnotation' => false
            ],
            'backend master request' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => false,
                'hasTheme' => false,
                'deletedAnnotation' => false
            ],
            'frontend master request without layout theme' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => true,
                'hasTheme' => false,
                'deletedAnnotations' => '_layout'
            ],
            'frontend sub-request without layout theme' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::SUB_REQUEST,
                'isFrontendRequest' => true,
                'hasTheme' => false,
                'deletedAnnotations' => '_layout'
            ],
            'frontend master request with layout theme' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::MASTER_REQUEST,
                'isFrontendRequest' => true,
                'hasTheme' => true,
                'deletedAnnotations' => '_template'
            ],
            'frontend sub-request with layout theme' => [
                'installed' => true,
                'requestType' => HttpKernelInterface::SUB_REQUEST,
                'isFrontendRequest' => true,
                'hasTheme' => true,
                'deletedAnnotations' => '_template'
            ],
        ];
    }
}
