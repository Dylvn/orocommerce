<?php

namespace Oro\Bundle\RedirectBundle\Tests\Unit\Generator;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\RedirectBundle\DependencyInjection\Configuration;
use Oro\Bundle\RedirectBundle\DependencyInjection\OroRedirectExtension;
use Oro\Bundle\RedirectBundle\Entity\Slug;
use Oro\Bundle\RedirectBundle\Entity\SluggableInterface;
use Oro\Bundle\RedirectBundle\Generator\CanonicalUrlGenerator;
use Oro\Bundle\RedirectBundle\Tests\Unit\Entity\SluggableEntityStub;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Component\Routing\RouteData;
use Symfony\Component\HttpFoundation\Request;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class CanonicalUrlGeneratorTest extends AbstractCanonicalUrlGeneratorTestCase
{
    protected function createGenerator(): CanonicalUrlGenerator
    {
        return new CanonicalUrlGenerator(
            $this->configManager,
            $this->cache,
            $this->requestStack,
            $this->routingInformationProvider,
            $this->websiteUrlResolver,
            $this->localizationProvider
        );
    }

    public function testIsDirectUrlEnabled()
    {
        /** @var Website $website */
        $website = $this->getEntity(Website::class, ['id' => 1]);

        $key = 'oro_redirect.canonical_url_type';
        $cacheKey = 'oro_redirect.canonical_url_type.1';
        $this->configManager->expects($this->once())
            ->method('get')
            ->with($key, false, false, $website)
            ->willReturn(Configuration::DIRECT_URL);
        $this->cache->expects($this->once())
            ->method('contains')
            ->with($cacheKey)
            ->willReturn(false);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheKey, Configuration::DIRECT_URL);
        $this->cache->expects($this->once())
            ->method('fetch')
            ->with($cacheKey)
            ->willReturn(Configuration::DIRECT_URL);

        $this->assertTrue($this->canonicalUrlGenerator->isDirectUrlEnabled($website));
    }

    public function testIsDirectUrlDisabled()
    {
        /** @var Website $website */
        $website = $this->getEntity(Website::class, ['id' => 1]);

        $key = 'oro_redirect.canonical_url_type';
        $cacheKey = 'oro_redirect.canonical_url_type.1';
        $this->configManager->expects($this->never())
            ->method('get')
            ->with($key, false, false, $website);
        $this->cache->expects($this->once())
            ->method('contains')
            ->with($cacheKey)
            ->willReturn(true);
        $this->cache->expects($this->never())
            ->method('save');
        $this->cache->expects($this->once())
            ->method('fetch')
            ->with($cacheKey)
            ->willReturn(Configuration::SYSTEM_URL);

        $this->assertFalse($this->canonicalUrlGenerator->isDirectUrlEnabled($website));
    }

    public function testGetDirectUrlForInsecureCanonical()
    {
        $canonicalPath = '/canonical';
        $expectedWebsiteUrl = 'http://example.com/';
        $expectedUrl = 'http://example.com/index_dev.php/canonical';
        $expectedBaseUrl = '/index_dev.php';
        $urlSecurityType = Configuration::INSECURE;
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsiteUrl')
            ->with($website)
            ->willReturn($expectedWebsiteUrl);

        $slug = new Slug();
        $slug->setUrl($canonicalPath);
        $slug->setRouteName('route_name');
        $slug->setRouteParameters([]);

        $this->assertUrlTypeCalls($urlSecurityType, $website);

        $this->cache->expects($this->any())
            ->method('contains')
            ->willReturn(false);

        $entity = $this->getSluggableEntity($slug);
        $this->assertRequestCalls($entity, $expectedBaseUrl);
        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getUrl($entity, null, $website));
    }

    public function testGetDirectUrlForInsecureCanonicalWithInstallationInSubfolder()
    {
        $canonicalPath = '/canonical';
        $expectedWebsiteUrl = 'http://example.com/subfolder';
        $expectedUrl = 'http://example.com/subfolder/index_dev.php/canonical';
        $expectedBaseUrl = '/subfolder/index_dev.php';
        $urlSecurityType = Configuration::INSECURE;
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsiteUrl')
            ->with($website)
            ->willReturn($expectedWebsiteUrl);

        $slug = new Slug();
        $slug->setUrl($canonicalPath);
        $slug->setRouteName('route_name');
        $slug->setRouteParameters([]);

        $this->assertUrlTypeCalls($urlSecurityType, $website);
        $entity = $this->getSluggableEntity($slug);
        $this->assertRequestCalls($entity, $expectedBaseUrl);
        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getUrl($entity, null, $website));
    }

    public function testGetDirectUrlForSecureCanonical()
    {
        $canonicalPath = '/canonical';
        $expectedWebsiteUrl = 'https://example.com/';
        $expectedUrl = 'https://example.com/index_dev.php/canonical';
        $expectedBaseUrl = '/index_dev.php';
        $urlSecurityType = Configuration::SECURE;
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsiteSecureUrl')
            ->with($website)
            ->willReturn($expectedWebsiteUrl);

        $slug = new Slug();
        $slug->setUrl($canonicalPath);
        $slug->setRouteName('route_name');
        $slug->setRouteParameters([]);

        $this->assertUrlTypeCalls($urlSecurityType, $website);
        $entity = $this->getSluggableEntity($slug);
        $this->assertRequestCalls($entity, $expectedBaseUrl);
        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getUrl($entity, null, $website));
    }

    /**
     * @dataProvider localizedUrlDataProvider
     */
    public function testGetDirectUrl(
        string $expectedUrl,
        Localization $localization = null,
        Localization $currentLocalization = null
    ) {
        $expectedWebsiteUrl = 'http://example.com/';

        $this->configManager->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['oro_redirect.use_localized_canonical', false, false, null, true]
            ]);
        $this->cache->expects($this->any())
            ->method('fetch')
            ->willReturnMap([
                ['oro_redirect.use_localized_canonical', true]
            ]);

        /** @var Localization $localization1 */
        $localization1 = $this->getEntity(Localization::class, ['id' => 1]);
        /** @var Localization $localization2 */
        $localization2 = $this->getEntity(Localization::class, ['id' => 2]);

        $this->localizationProvider->expects($this->any())
            ->method('getCurrentLocalization')
            ->willReturn($currentLocalization);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsiteUrl')
            ->willReturn($expectedWebsiteUrl);

        $baseSlug = new Slug();
        $baseSlug->setUrl('/canonical_base');
        $baseSlug->setRouteName('route_name');
        $baseSlug->setRouteParameters([]);

        $slug = new Slug();
        $slug->setUrl('/canonical');
        $slug->setRouteName('route_name');
        $slug->setRouteParameters([]);
        $slug->setLocalization($localization1);

        $slug2 = new Slug();
        $slug2->setUrl('/canonical_2');
        $slug2->setRouteName('route_name');
        $slug2->setRouteParameters([]);
        $slug2->setLocalization($localization2);

        $this->assertUrlTypeCalls(Configuration::INSECURE);
        $entity = new SluggableEntityStub();
        $entity->addSlug($baseSlug);
        $entity->addSlug($slug);
        $entity->addSlug($slug2);
        $this->assertRequestCalls($entity);

        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getDirectUrl($entity, $localization));
    }

    public function localizedUrlDataProvider()
    {
        yield 'current used' => [
            'http://example.com/canonical',
            null,
            $this->getEntity(Localization::class, ['id' => 1])
        ];

        yield 'base used when not found for current' => [
            'http://example.com/canonical_base',
            null,
            $this->getEntity(Localization::class, ['id' => 3])
        ];

        yield 'base used when no current' => [
            'http://example.com/canonical_base',
            null,
            null
        ];

        yield 'by locale no current' => [
            'http://example.com/canonical',
            $this->getEntity(Localization::class, ['id' => 1]),
            null
        ];

        yield 'by locale with another current' => [
            'http://example.com/canonical_2',
            $this->getEntity(Localization::class, ['id' => 2]),
            $this->getEntity(Localization::class, ['id' => 1])
        ];
    }

    public function testGetSystemUrlForInsecureCanonical()
    {
        $expectedUrl = 'http://example.com/canonical';
        $website = $this->getEntity(Website::class, ['id' => 1]);

        $data = $this->createMock(SluggableInterface::class);

        $route = 'route';
        $routeParameters = ['param' => 42];
        $routeData = new RouteData($route, $routeParameters);

        $this->cache->expects($this->any())
            ->method('contains')
            ->willReturn(false);

        $this->assertUrlTypeCalls(Configuration::INSECURE, $website);

        $this->routingInformationProvider->expects($this->once())
            ->method('getRouteData')
            ->with($data)
            ->willReturn($routeData);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsitePath')
            ->with($route, $routeParameters, $website)
            ->willReturn($expectedUrl);

        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getUrl($data, null, $website));
    }

    public function testGetSystemUrlForSecureCanonical()
    {
        $expectedUrl = 'https://example.com/canonical';
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $data = $this->createMock(SluggableInterface::class);

        $route = 'route';
        $routeParameters = ['param' => 42];
        $routeData = new RouteData($route, $routeParameters);

        $this->cache->expects($this->any())
            ->method('contains')
            ->willReturn(false);

        $this->assertUrlTypeCalls(Configuration::SECURE, $website);

        $this->routingInformationProvider->expects($this->once())
            ->method('getRouteData')
            ->with($data)
            ->willReturn($routeData);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsiteSecurePath')
            ->with($route, $routeParameters, $website)
            ->willReturn($expectedUrl);

        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getUrl($data, null, $website));
    }

    public function testGetUrlWithoutDirect()
    {
        $data = $this->createMock(SluggableInterface::class);
        $data->expects($this->any())
            ->method('getSlugs')
            ->willReturn(new ArrayCollection([]));

        $this->cache->expects($this->any())
            ->method('contains')
            ->willReturnMap([
                ['oro_redirect.canonical_url_type', true],
                ['oro_redirect.canonical_url_security_type', true],
                ['oro_redirect.use_localized_canonical', true]
            ]);

        $this->cache->expects($this->any())
            ->method('fetch')
            ->willReturnMap([
                ['oro_redirect.canonical_url_type', Configuration::DIRECT_URL],
                ['oro_redirect.canonical_url_security_type', Configuration::INSECURE],
                ['oro_redirect.use_localized_canonical', true]
            ]);

        $this->configManager->expects($this->never())->method('get');

        $expectedUrl = 'http://example.com/canonical';

        $route = 'route';
        $routeParameters = ['param' => 42];
        $routeData = new RouteData($route, $routeParameters);

        $this->routingInformationProvider->expects($this->once())
            ->method('getRouteData')
            ->with($data)
            ->willReturn($routeData);

        $this->websiteUrlResolver->expects($this->any())
            ->method('getWebsitePath')
            ->with($route, $routeParameters)
            ->willReturn($expectedUrl);

        $this->assertEquals($expectedUrl, $this->canonicalUrlGenerator->getUrl($data));
    }

    public function testClearCacheWithoutWebsite()
    {
        $this->cache->expects($this->exactly(3))
            ->method('delete')
            ->withConsecutive(
                [sprintf('%s.%s', OroRedirectExtension::ALIAS, Configuration::CANONICAL_URL_TYPE)],
                [sprintf('%s.%s', OroRedirectExtension::ALIAS, Configuration::CANONICAL_URL_SECURITY_TYPE)],
                [sprintf('%s.%s', OroRedirectExtension::ALIAS, Configuration::USE_LOCALIZED_CANONICAL)]
            );
        $this->canonicalUrlGenerator->clearCache();
    }

    public function testClearCacheWithWebsite()
    {
        $website = $this->getEntity(Website::class, ['id' => 777]);
        $this->cache->expects($this->exactly(3))
            ->method('delete')
            ->withConsecutive(
                [
                    sprintf(
                        '%s.%s.%s',
                        OroRedirectExtension::ALIAS,
                        Configuration::CANONICAL_URL_TYPE,
                        777
                    )
                ],
                [
                    sprintf(
                        '%s.%s.%s',
                        OroRedirectExtension::ALIAS,
                        Configuration::CANONICAL_URL_SECURITY_TYPE,
                        777
                    )
                ],
                [sprintf('%s.%s', OroRedirectExtension::ALIAS, Configuration::USE_LOCALIZED_CANONICAL)]
            );

        $this->canonicalUrlGenerator->clearCache($website);
    }

    public function testGetCanonicalDomainUrlSecure()
    {
        $host = 'https://host.domain';
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with('oro_redirect.canonical_url_security_type', false, false, $website)
            ->willReturn(Configuration::SECURE);

        $this->cache->expects($this->any())
            ->method('fetch')
            ->with('oro_redirect.canonical_url_security_type.777')
            ->willReturn(Configuration::SECURE);

        $this->websiteUrlResolver->expects($this->atLeastOnce())
            ->method('getWebsiteSecureUrl')
            ->with($website)
            ->willReturn($host);

        $this->assertEquals($host, $this->canonicalUrlGenerator->getCanonicalDomainUrl($website));
    }

    public function testGetCanonicalDomainUrlNotSecure()
    {
        $host = 'http://host.domain';
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with('oro_redirect.canonical_url_security_type', false, false, $website)
            ->willReturn(Configuration::INSECURE);

        $this->cache->expects($this->any())
            ->method('fetch')
            ->with('oro_redirect.canonical_url_security_type.777')
            ->willReturn(Configuration::INSECURE);

        $this->websiteUrlResolver->expects($this->atLeastOnce())
            ->method('getWebsiteUrl')
            ->with($website)
            ->willReturn($host);

        $this->assertEquals($host, $this->canonicalUrlGenerator->getCanonicalDomainUrl($website));
    }

    public function testGetAbsoluteUrl()
    {
        $host = 'https://host.domain/hello/';
        $url = '/test/my/url';
        $website = $this->getEntity(Website::class, ['id' => 777]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with('oro_redirect.canonical_url_security_type', false, false, $website)
            ->willReturn(Configuration::SECURE);

        $this->cache->expects($this->any())
            ->method('fetch')
            ->with('oro_redirect.canonical_url_security_type.777')
            ->willReturn(Configuration::SECURE);

        $this->websiteUrlResolver->expects($this->atLeastOnce())
            ->method('getWebsiteSecureUrl')
            ->with($website)
            ->willReturn($host);

        $this->assertEquals(
            'https://host.domain/hello/test/my/url',
            $this->canonicalUrlGenerator->getAbsoluteUrl($url, $website)
        );
    }

    public function testCreateUrlWithMasterRequest()
    {
        /** @var Request|\PHPUnit\Framework\MockObject\MockObject $request */
        $request = $this->createMock(Request::class);
        $request->expects($this->any())
            ->method('getBaseUrl')
            ->willReturn('base');
        $this->requestStack->expects($this->atMost(1))
            ->method('getMainRequest')
            ->willReturn($request);

        $host = 'https://host.domain/';
        $url = '/test/my/url';

        $this->assertEquals(
            'https://host.domain/base/test/my/url',
            $this->canonicalUrlGenerator->createUrl($host, $url)
        );
    }

    public function testCreateUrlWithoutMasterRequest()
    {
        $this->requestStack->expects($this->atMost(1))
            ->method('getMainRequest')
            ->willReturn(null);

        $host = 'https://host.domain/';
        $url = '/test/my/url';

        $this->assertEquals(
            'https://host.domain/test/my/url',
            $this->canonicalUrlGenerator->createUrl($host, $url)
        );
    }
}
