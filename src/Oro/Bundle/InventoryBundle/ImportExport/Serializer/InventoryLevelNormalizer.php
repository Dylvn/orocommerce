<?php

namespace Oro\Bundle\InventoryBundle\ImportExport\Serializer;

use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\ImportExportBundle\Event\Events;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\ConfigurableEntityNormalizer;
use Oro\Bundle\InventoryBundle\Entity\InventoryLevel;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\Formatter\UnitLabelFormatterInterface;
use Oro\Bundle\ProductBundle\Rounding\QuantityRoundingService;

/**
 * Normalizes InventoryLevel class instances (depends on mode)
 */
class InventoryLevelNormalizer extends ConfigurableEntityNormalizer
{
    /**
     * @var UnitLabelFormatterInterface
     */
    private $formatter;

    /**
     * @var QuantityRoundingService
     */
    private $roundingService;

    public function __construct(
        FieldHelper $fieldHelper,
        UnitLabelFormatterInterface $formatter,
        QuantityRoundingService $roundingService
    ) {
        parent::__construct($fieldHelper);

        $this->formatter = $formatter;
        $this->roundingService = $roundingService;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof InventoryLevel;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        $result = $this->dispatchNormalize($object, [], $context, Events::BEFORE_NORMALIZE_ENTITY);

        // Set quantity to null if not exporting real object
        if (!$object->getId() && 0 == $object->getQuantity()) {
            $object->setQuantity(null);
        }

        /** @var Product $product */
        $product = $object->getProduct();

        $result['quantity'] = $this->getQuantity($object);

        if ($product) {
            $result['product'] = [
                'sku' => $product->getSku(),
                'defaultName' => $product->getDefaultName() ? $product->getDefaultName()->getString() : null,
                'inventoryStatus' => ($product->getInventoryStatus()) ? $product->getInventoryStatus()->getName() : null
            ];
        }

        $result = array_merge($result, $this->getUnitPrecision($object));

        return $this->dispatchNormalize($object, $result, $context, Events::AFTER_NORMALIZE_ENTITY);
    }

    /**
     * @param InventoryLevel $inventoryLevel
     * @return float|int
     */
    protected function getQuantity(InventoryLevel $inventoryLevel)
    {
        $productUnit = $inventoryLevel->getProductUnitPrecision()
            ? $inventoryLevel->getProductUnitPrecision()->getUnit()
            : null;
        if (!$productUnit) {
            return $inventoryLevel->getQuantity();
        }

        return $this->roundingService->roundQuantity(
            $inventoryLevel->getQuantity(),
            $productUnit,
            $inventoryLevel->getProduct()
        );
    }

    /**
     * @param InventoryLevel $inventoryLevel
     * @return array
     */
    protected function getUnitPrecision(InventoryLevel $inventoryLevel)
    {
        $unitPrecision = $inventoryLevel->getProductUnitPrecision();
        if (!$unitPrecision) {
            return [];
        }
        $code = $unitPrecision->getUnit() ? $unitPrecision->getUnit()->getCode() : null;
        $code = $code ? $this->formatter->format($code, false, $inventoryLevel->getQuantity() > 1) : null;

        return ['productUnitPrecision' => [
            'unit' => [
                'code' => $code
            ]
        ]];
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        if (!is_array($data) || !isset($data['product'])) {
            return null;
        }

        /** @var InventoryLevel $inventoryLevel */
        $inventoryLevel = $this->dispatchDenormalize(
            $data,
            $this->createObject($type),
            Events::BEFORE_DENORMALIZE_ENTITY
        )->getObject();

        $productData = $data['product'];

        $productEntity = new Product();
        $productEntity->setSku($productData['sku']);

        $productUnitPrecision = new ProductUnitPrecision();

        $productUnitPrecision->setProduct($productEntity);

        if (array_key_exists('inventoryStatus', $productData)) {
            $productEntity->setInventoryStatus($productData['inventoryStatus']);
        }

        $this->determineQuantity($inventoryLevel, $data);

        if (array_key_exists('productUnitPrecision', $data)) {
            $productUnitPrecisionData = $data['productUnitPrecision'];

            $productUnit = new ProductUnit();
            $productUnit->setCode(
                isset($productUnitPrecisionData['unit']) ? $productUnitPrecisionData['unit']['code'] : ''
            );
            $productUnitPrecision->setUnit($productUnit);
        }

        $inventoryLevel->setProductUnitPrecision($productUnitPrecision);

        return $this->dispatchDenormalize($data, $inventoryLevel, Events::AFTER_DENORMALIZE_ENTITY)->getObject();
    }

    protected function determineQuantity(InventoryLevel $inventoryLevel, array $data)
    {
        if (array_key_exists('quantity', $data)) {
            $inventoryLevel->setQuantity($data['quantity'] ?: 0);
        } else {
            $inventoryLevel->setQuantity(null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null, array $context = array()): bool
    {
        return !empty($data) && isset($data['product']) && $type === InventoryLevel::class;
    }
}
