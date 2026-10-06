<?php

namespace Database\Seeders;

use ClassyFashion\Support\Brand;
use Illuminate\Database\Seeder;
use Webkul\Core\Models\Channel;
use Webkul\Core\Models\CoreConfig;

/**
 * Single Classy Fashion Hub brand in the database.
 *
 * Fills the DB-driven brand slots so Bagisto fallbacks (Webkul footer,
 * Shop sender name, Demo store SEO, empty invoice footer) never render.
 * Idempotent: safe to run multiple times. A response middleware rewrites
 * any hardcoded fallback that remains in Blade or lang files.
 */
class ClassyBrandSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedConfig('general.content.footer.copyright_content', Brand::copyright(), null, 'en');

        $this->seedConfig('emails.configure.email_settings.sender_name', Brand::NAME, 'default', null);

        $this->seedConfig('emails.configure.email_settings.admin_name', Brand::NAME, 'default', null);

        $this->seedConfig('sales.invoice_settings.pdf_print_outs.footer_text', Brand::copyright(), 'default', 'en');

        $this->seedConfig(
            'sales.invoice_settings.pdf_print_outs.logo',
            'channel/cfh-logo-brandkit.png',
            'default',
            null
        );

        $this->seedConfig(
            'general.design.admin_logo.logo_image',
            'channel/cfh-logo-brandkit.png',
            null,
            null
        );

        $this->seedConfig(
            'general.design.admin_logo.favicon',
            'channel/cfh-favicon-brandkit.png',
            null,
            null
        );

        $channel = Channel::where('code', 'default')->first();

        if ($channel) {
            $translation = $channel->translate('en');

            if ($translation) {
                $translation->forceFill([
                    'logo_alt' => Brand::NAME,
                    'home_seo' => [
                        'meta_title' => Brand::NAME,
                        'meta_description' => 'Classy Fashion Hub — shirts, jackets, shoes and more. Clear prices, easy ordering.',
                        'meta_keywords' => 'Classy Fashion Hub, fashion, Uganda, shirts, jackets, shoes',
                    ],
                ])->save();
            }
        }
    }

    protected function seedConfig(string $code, string $value, ?string $channel, ?string $locale): void
    {
        $criteria = ['code' => $code];

        if ($channel !== null) {
            $criteria['channel_code'] = $channel;
        }

        if ($locale !== null) {
            $criteria['locale_code'] = $locale;
        }

        $existing = CoreConfig::where($criteria)->first();

        if ($existing) {
            $existing->forceFill(['value' => $value])->save();

            return;
        }

        CoreConfig::create($criteria + ['value' => $value]);
    }
}
