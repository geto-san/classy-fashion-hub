<div class="cfh-product-gallery-mobile">
    <div ref="mobileRail" class="cfh-product-gallery-mobile-rail" @scroll.passive="syncMobileIndex">
        <div v-for="(media, index) in galleryMedia" :key="media.original_image_url || media.video_url || index" class="cfh-product-gallery-mobile-slide">
            <video v-if="media.type === 'videos'" controls preload="metadata" @loadedmetadata="onMediaLoad" class="cfh-product-gallery-video">
                <source :src="media.video_url" type="video/mp4">
            </video>
            <button v-else type="button" class="cfh-product-gallery-open" @click="openMedia(index)" :aria-label="(media.alt || @js($product->name)) + ' ' + (index + 1)">
                <img :src="media.large_image_url" :alt="media.alt || @js($product->name)" width="432" height="529" @load="onMediaLoad" v-on:error="onMediaError($event)" :fetchpriority="index === 0 ? 'high' : 'auto'">
            </button>
        </div>
    </div>
    <div class="cfh-product-gallery-pagination" v-if="galleryMedia.length > 1">
        <button v-for="(media, index) in galleryMedia" :key="index" type="button" :class="{ 'is-active': activeIndex === index }" :aria-label="(media.alt || @js($product->name)) + ' ' + (index + 1)" :aria-pressed="activeIndex === index" @click="selectMobileMedia(index)"></button>
    </div>
</div>
