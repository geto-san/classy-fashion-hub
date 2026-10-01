<?php

namespace ClassyFashion\Listeners;

use Webkul\Theme\ViewRenderEventManager;

/**
 * Add a delivery-instructions field to the shipping address form at
 * checkout (report 9.7). Rendered only for the shipping form; the value
 * posts with the address and is stored on the cart/order address.
 */
class CheckoutDeliveryField
{
    public function addField(ViewRenderEventManager $eventManager): void
    {
        $label = e(__('classy-fashion::app.checkout.delivery_instructions'));

        $placeholder = e(__('classy-fashion::app.checkout.delivery_instructions_placeholder'));

        $html = <<<HTML
        <div
            class="mt-2 max-md:mt-3"
            v-if="controlName === 'shipping'"
        >
            <label class="mb-1.5 block text-sm font-medium text-zinc-500 ltr:pl-0 rtl:pr-0 max-sm:text-xs">
                {$label}
            </label>

            <v-field
                v-slot="{ field, errors }"
                :name="controlName + '.delivery_instructions'"
                :value="address.delivery_instructions"
                rules="max:500"
            >
                <textarea
                    :name="controlName + '.delivery_instructions'"
                    v-bind="field"
                    maxlength="500"
                    rows="2"
                    placeholder="{$placeholder}"
                    class="mb-1.5 w-full rounded-lg border px-5 py-3 text-base font-normal text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 max-sm:px-4 max-md:py-2 max-sm:text-sm"
                ></textarea>
            </v-field>

            <v-error-message
                :name="controlName + '.delivery_instructions'"
                class="text-xs font-medium text-red-500"
            />
        </div>
        HTML;

        $eventManager->addTemplate($html);
    }
}
