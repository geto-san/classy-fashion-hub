<?php

use ClassyFashion\Support\ProductImage;

it('prefers a supplied real photo, in gallery order, over the placeholder', function () {
    $dir = base_path(ProductImage::PHOTO_DIR);

    $first = $dir.'/zz-test-jacket.jpg';
    $second = $dir.'/zz-test-jacket-2.png';

    file_put_contents($first, 'x');
    file_put_contents($second, 'y');

    try {
        expect(ProductImage::photos('ZZ Test Jacket'))->toBe([$first, $second]);
    } finally {
        @unlink($first);
        @unlink($second);
    }

    expect(ProductImage::photos('ZZ Test Jacket'))->toBe([]);
});
