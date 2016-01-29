<?php

namespace OroB2B\Bundle\AccountBundle\Tests\Functional\Visibility\Cache\Product\Category;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\AbstractQuery;

use Oro\Component\Testing\WebTestCase;

use OroB2B\Bundle\AccountBundle\Entity\Account;
use OroB2B\Bundle\CatalogBundle\Entity\Category;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\CategoryVisibility;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\VisibilityInterface;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\CategoryVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\BaseCategoryVisibilityResolved;
use OroB2B\Bundle\CatalogBundle\Tests\Functional\DataFixtures\LoadCategoryData;
use OroB2B\Bundle\AccountBundle\Visibility\Cache\Product\Category\ProductResolvedCacheBuilder;

/**
 * @dbIsolation
 */
class ProductResolvedCacheBuilderTest extends WebTestCase
{
    const ROOT = 'root';

    /**
     * @var ProductResolvedCacheBuilder
     */
    protected $builder;

    /** @var Registry */
    protected $registry;

    /** @var Category */
    protected $category;

    /** @var Account */
    protected $account;

    protected function setUp()
    {
        $this->initClient();

        $this->loadFixtures([
            'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadCategoryVisibilityData',
        ]);

        $this->registry = $this->client->getContainer()->get('doctrine');
        $this->category = $this->getReference(LoadCategoryData::SECOND_LEVEL1);
        $this->account = $this->getReference('account.level_1');
        $this->builder = $this->getContainer()
            ->get('orob2b_account.visibility.cache.product.category.product_resolved_cache_builder');
    }

    public function tearDown()
    {
        $this->getContainer()->get('doctrine')->getManager()->clear();
        parent::tearDown();
    }

    public function testChangeCategoryVisibilityToHidden()
    {
        $visibility = new CategoryVisibility();
        $visibility->setCategory($this->category);
        $visibility->setVisibility(CategoryVisibility::HIDDEN);

        $em = $this->registry->getManagerForClass('OroB2BAccountBundle:Visibility\CategoryVisibility');
        $em->persist($visibility);
        $em->flush();

        $visibilityResolved = $this->getVisibilityResolved();
        $this->assertStatic($visibilityResolved, $visibility, BaseCategoryVisibilityResolved::VISIBILITY_HIDDEN);
    }

    /**
     * @depends testChangeCategoryVisibilityToHidden
     */
    public function testChangeCategoryVisibilityToVisible()
    {
        $visibility = $this->getVisibility();
        $visibility->setVisibility(CategoryVisibility::VISIBLE);

        $em = $this->registry->getManagerForClass('OroB2BAccountBundle:Visibility\CategoryVisibility');
        $em->flush();

        $visibilityResolved = $this->getVisibilityResolved();
        $this->assertStatic($visibilityResolved, $visibility, BaseCategoryVisibilityResolved::VISIBILITY_VISIBLE);
    }

    /**
     * @depends testChangeCategoryVisibilityToHidden
     */
    public function testChangeCategoryVisibilityToConfig()
    {
        $visibility = $this->getVisibility();
        $visibility->setVisibility(CategoryVisibility::CONFIG);

        $em = $this->registry->getManagerForClass('OroB2BAccountBundle:Visibility\CategoryVisibility');
        $em->flush();

        $this->assertNull($this->getVisibilityResolved());
    }

    /**
     * @depends testChangeCategoryVisibilityToConfig
     */
    public function testChangeCategoryVisibilityToParentCategory()
    {
        $visibility = $this->getVisibility();
        $visibility->setVisibility(CategoryVisibility::PARENT_CATEGORY);

        $em = $this->registry->getManagerForClass('OroB2BAccountBundle:Visibility\CategoryVisibility');
        $em->flush();

        $visibilityResolved = $this->getVisibilityResolved();
        $this->assertNull($visibilityResolved['sourceCategoryVisibility']['visibility']);
        $this->assertEquals(BaseCategoryVisibilityResolved::SOURCE_PARENT_CATEGORY, $visibilityResolved['source']);
        $this->assertEquals($this->category->getId(), $visibilityResolved['category_id']);
        $this->assertEquals(
            BaseCategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG,
            $visibilityResolved['visibility']
        );
    }

    /**
     * @return array
     */
    protected function getVisibilityResolved()
    {
        $em = $this->registry->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\CategoryVisibilityResolved');
        $qb = $em->getRepository('OroB2BAccountBundle:VisibilityResolved\CategoryVisibilityResolved')
            ->createQueryBuilder('CategoryVisibilityResolved');
        $entity = $qb->select('CategoryVisibilityResolved', 'CategoryVisibility')
            ->leftJoin('CategoryVisibilityResolved.sourceCategoryVisibility', 'CategoryVisibility')
            ->where(
                $qb->expr()->eq('CategoryVisibilityResolved.category', ':category')
            )
            ->setParameters([
            'category' => $this->category,
            ])
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        return $entity;
    }

    /**
     * @return null|CategoryVisibility
     */
    protected function getVisibility()
    {
        return $this->registry->getManagerForClass('OroB2BAccountBundle:Visibility\CategoryVisibility')
            ->getRepository('OroB2BAccountBundle:Visibility\CategoryVisibility')
            ->findOneBy(['category' => $this->category]);
    }

    /**
     * @param array $categoryVisibilityResolved
     * @param VisibilityInterface $categoryVisibility
     * @param integer $expectedVisibility
     */
    protected function assertStatic(
        array $categoryVisibilityResolved,
        VisibilityInterface $categoryVisibility,
        $expectedVisibility
    ) {
        $this->assertNotNull($categoryVisibilityResolved);
        $this->assertEquals($this->category->getId(), $categoryVisibilityResolved['category_id']);
        $this->assertEquals(CategoryVisibilityResolved::SOURCE_STATIC, $categoryVisibilityResolved['source']);
        $this->assertEquals(
            $categoryVisibility->getVisibility(),
            $categoryVisibilityResolved['sourceCategoryVisibility']['visibility']
        );
        $this->assertEquals($expectedVisibility, $categoryVisibilityResolved['visibility']);
    }
    public function testBuildCache()
    {
        $expectedVisibilities = [
            [
            'category' => self::ROOT,
            'visibility' => CategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG,
            'source' => CategoryVisibilityResolved::SOURCE_STATIC,
            ],
            [
            'category' => 'category_1',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_VISIBLE,
            'source' => CategoryVisibilityResolved::SOURCE_STATIC,
            ],
            [
            'category' => 'category_1_2',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_VISIBLE,
            'source' => CategoryVisibilityResolved::SOURCE_PARENT_CATEGORY,
            ],
            [
            'category' => 'category_1_2_3',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_VISIBLE,
            'source' => CategoryVisibilityResolved::SOURCE_STATIC,
            ],
            [
            'category' => 'category_1_2_3_4',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_VISIBLE,
            'source' => CategoryVisibilityResolved::SOURCE_PARENT_CATEGORY,
            ],
            [
            'category' => 'category_1_5',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_VISIBLE,
            'source' => CategoryVisibilityResolved::SOURCE_PARENT_CATEGORY,
            ],
            [
            'category' => 'category_1_5_6',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_HIDDEN,
            'source' => CategoryVisibilityResolved::SOURCE_STATIC,
            ],
            [
            'category' => 'category_1_5_6_7',
            'visibility' => CategoryVisibilityResolved::VISIBILITY_HIDDEN,
            'source' => CategoryVisibilityResolved::SOURCE_PARENT_CATEGORY,
            ],
        ];
        $expectedVisibilities = $this->replaceReferencesWithIds($expectedVisibilities);
        usort($expectedVisibilities, [$this, 'sortByCategory']);

        $this->builder->buildCache();

        $actualVisibilities = $this->getResolvedVisibilities();
        usort($actualVisibilities, [$this, 'sortByCategory']);

        $this->assertEquals($expectedVisibilities, $actualVisibilities);
    }

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    protected function sortByCategory(array $a, array $b)
    {
        return $a['category'] > $b['category'] ? 1 : -1;
    }

    /**
     * @param array $categories
     * @return array
     */
    protected function replaceReferencesWithIds(array $categories)
    {
        $rootCategory = $this->getRootCategory();

        foreach ($categories as $key => $row) {
            $category = $row['category'];
            /** @var Category $category */
            if ($category === self::ROOT) {
                $category = $rootCategory;
            } else {
                $category = $this->getReference($category);
            }
            $categories[$key]['category'] = $category->getId();
        }

        return $categories;
    }

    /**
     * @return array
     */
    protected function getResolvedVisibilities()
    {
        return $this->getContainer()->get('doctrine')
            ->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\CategoryVisibilityResolved')
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\CategoryVisibilityResolved')
            ->createQueryBuilder('entity')
            ->select(
                'IDENTITY(entity.category) as category',
                'entity.visibility',
                'entity.source'
            )
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return Category
     */
    protected function getRootCategory()
    {
        return $this->getContainer()->get('doctrine')
            ->getManagerForClass('OroB2BCatalogBundle:Category')
            ->getRepository('OroB2BCatalogBundle:Category')
            ->getMasterCatalogRoot();
    }
}
