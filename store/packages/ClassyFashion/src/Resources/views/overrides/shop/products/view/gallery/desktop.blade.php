<div class="cfh-product-gallery-desktop">
    <template v-for="(media, index) in galleryMedia" :key="media.original_image_url || media.video_url || index">
        <div class="cfh-product-gallery-item">
            <video v-if="media.type === 'videos'" controls preload="metadata" @loadedmetadata="onMediaLoad" class="cfh-product-gallery-video">
                <source :src="media.video_url" type="video/mp4">
            </video>
            <button v-else type="button" class="cfh-product-gallery-open" @click="openMedia(index)" :aria-label="(media.alt || @js($product->name)) + ' ' + (index + 1)">
                <img :src="media.large_image_url" :alt="media.alt || @js($product->name)" width="432" height="529" @load="onMediaLoad" v-on:error="onMediaError($event)" :fetchpriority="index === 0 ? 'high' : 'auto'">
            </button>
        </div>
    </template>

    @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
        <button type="button" class="cfh-product-gallery-wishlist" :class="isWishlist ? 'icon-heart-fill' : 'icon-heart'" :aria-pressed="isWishlist" aria-label="{{ trans('shop::app.products.view.add-to-wishlist') }}" @click="$emit('wishlist')"></button>
    @endif
</div>
