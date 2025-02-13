<?php

declare(strict_types=1);

namespace NuonicPaymentMethodSurcharge\Core\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

readonly class PaymentMethodChargeProcessor implements CartProcessorInterface
{
    public const LINE_ITEM_REFERENCE = 'NuonicPaymentMethodSurcharge';
    public const LINE_ITEM_TYPE = 'nuonic-payment-method-surcharge';

    public function __construct(
        private PercentagePriceCalculator $calculator,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior,
    ): void {
        $products = $original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        if (0 === count($products)) {
            return;
        }

        if (!$data->has(__CLASS__)) {
            return;
        }

        $lineItemData = $data->get(__CLASS__);

        $lineItem = new LineItem(
            $original->getLineItems()->filterType(self::LINE_ITEM_TYPE)->first()?->getId() ?? Uuid::randomHex(),
            self::LINE_ITEM_TYPE,
            self::LINE_ITEM_REFERENCE,
            1
        );

        $lineItem->setLabel($lineItemData['chargeLineItemLabel']);
        $lineItem->setGood(false);
        $lineItem->setStackable(false);
        $lineItem->setRemovable(false);

        $definition = new PercentagePriceDefinition($lineItemData['chargePercentage']);

        $price = $this->calculator->calculate(
            $definition->getPercentage(),
            $toCalculate->getLineItems()->getPrices(),
            $context
        );

        $lineItem->setPrice($price);
        $lineItem->setPriceDefinition($definition);

        $originalLineItem = $original->getLineItems()
            ->filter(fn (LineItem $lineItem) => self::LINE_ITEM_REFERENCE === $lineItem->getReferencedId())
            ->first();
        if (!is_null($originalLineItem)) {
            $lineItem->setExtensions($originalLineItem->getExtensions());
        }

        $toCalculate->add($lineItem);
    }
}
