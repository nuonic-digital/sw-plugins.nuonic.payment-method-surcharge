<?php

declare(strict_types=1);

namespace NuonicPaymentMethodSurcharge\Core\Checkout;

use NuonicPaymentMethodSurcharge\Config\PluginConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class PaymentMethodChargeCollector implements CartDataCollectorInterface
{
    public function __construct(
        private PluginConfigService $pluginConfigService,
        private TranslatorInterface $translator,
    ) {
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
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

        $lineItemLabel = trim($this->pluginConfigService->getString(
            'chargeLineItemLabel',
            $context->getSalesChannelId()
        ));

        if ('' === $lineItemLabel) {
            $lineItemLabel = $this->translator->trans('nuonicPaymentMethodSurcharge.defaultLineItemLabel');
        }

        $data->set(PaymentMethodChargeProcessor::class, [
            'chargePercentage' => $chargePercentage,
            'chargeLineItemLabel' => $lineItemLabel,
        ]);
    }
}
