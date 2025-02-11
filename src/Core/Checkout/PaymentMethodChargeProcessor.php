<?php

declare(strict_types=1);

namespace NuonicPaymentMethodSurcharge\Core\Checkout;

use NuonicPaymentMethodSurcharge\Config\PluginConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class PaymentMethodChargeProcessor implements CartProcessorInterface
{
    public const LINE_ITEM_REFERENCE = 'NuonicPaymentMethodSurcharge';

    public function __construct(
        private PercentagePriceCalculator $calculator,
        private PluginConfigService $pluginConfigService,
        private LineItemFactoryRegistry $lineItemFactory,
        private TranslatorInterface $translator,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior,
    ): void {
        $paymentMethodIds = $this->pluginConfigService->get('paymentMethodIds', $context->getSalesChannelId());
        if (!is_array($paymentMethodIds) || !in_array($context->getPaymentMethod()->getId(), $paymentMethodIds, true)) {
            // this will also return here, if paymentMethodId is null :)
            return;
        }

        // if no percentage is defined -> nothing to do
        try {
            $chargePercentage = $this->pluginConfigService->getFloat('chargePercentage', $context->getSalesChannelId());
        } catch (InvalidSettingValueException $e) {
            return;
        }

        if (FloatComparator::equals($chargePercentage, 0.0)) {
            return;
        }

        $context->setPermissions([...$context->getPermissions(), ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true]);

        $lineItem = $this->lineItemFactory->create([
            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
            'referencedId' => self::LINE_ITEM_REFERENCE,
            'quantity' => 1,
            'payload' => [],
        ], $context);

        $lineItemLabel = trim($this->pluginConfigService->getString(
            'chargeLineItemLabel',
            $context->getSalesChannelId()
        ));

        if ('' === $lineItemLabel) {
            $lineItemLabel = $this->translator->trans('nuonicPaymentMethodSurcharge.defaultLineItemLabel');
        }

        $lineItem->setLabel($lineItemLabel);
        $lineItem->setGood(false);
        $lineItem->setStackable(false);
        $lineItem->setRemovable(false);

        $definition = new PercentagePriceDefinition($chargePercentage);

        $lineItem->setPriceDefinition($definition);

        $lineItem->setPrice($this->calculator->calculate(
            $definition->getPercentage(),
            $toCalculate->getLineItems()->getPrices(),
            $context
        ));

        $originalLineItem = $original->getLineItems()
            ->filter(fn (LineItem $lineItem) => self::LINE_ITEM_REFERENCE === $lineItem->getReferencedId())
            ->first();
        if (!is_null($originalLineItem)) {
            $lineItem->setExtensions($originalLineItem->getExtensions());
        }

        $toCalculate->add($lineItem);
    }
}
