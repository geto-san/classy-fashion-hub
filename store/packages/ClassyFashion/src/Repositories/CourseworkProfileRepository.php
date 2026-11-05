<?php

namespace ClassyFashion\Repositories;

use Webkul\CMS\Repositories\PageRepository;
use Webkul\Core\Repositories\CoreConfigRepository;

/**
 * Publish the service information and settings needed for an academic walkthrough.
 */
class CourseworkProfileRepository
{
    /**
     * Keep configuration and static pages on their canonical repository paths.
     */
    public function __construct(protected CoreConfigRepository $configs, protected PageRepository $pages) {}

    /**
     * Configure working checkout without paid email verification or gateway services.
     */
    public function publish(array $presentation, int $channelId): void
    {
        $settings = $presentation['settings'] ?? [];

        foreach ($presentation['images'] ?? [] as $slot => $image) {
            $settings[$slot.'_image'] = $image['path'];
            $settings[$slot.'_alt'] = $image['alt'];
        }

        $this->configs->create([
            'channel' => 'default',
            'locale' => 'en',
            'general' => ['content' => ['storefront' => $settings]],
            'catalog' => [
                'inventory' => ['stock_options' => ['back_orders' => '0']],
                'products' => ['storefront' => ['sort_by' => 'created_at-desc']],
            ],
            'customer' => ['settings' => ['email' => ['verification' => '0']]],
            'sales' => ['payment_methods' => ['mobilemoney' => ['active' => '1'], 'cashondelivery' => ['active' => '1']]],
        ]);

        foreach ($presentation['pages'] ?? [] as $slug => $page) {
            if ($this->pages->findByUrlKey($slug)) {
                continue;
            }

            $this->pages->create([
                'page_title' => $page['title'],
                'url_key' => $slug,
                'html_content' => '<div class="static-container"><h1>'.e($page['title']).'</h1><p>'.e($page['text']).'</p></div>',
                'meta_title' => $page['title'].' | Classy Fashion Hub',
                'meta_description' => $page['text'],
                'meta_keywords' => '',
                'layout' => 'one-column',
                'channels' => [$channelId],
            ]);
        }
    }
}
