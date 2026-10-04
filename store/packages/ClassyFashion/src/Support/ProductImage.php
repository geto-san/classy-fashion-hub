<?php

namespace ClassyFashion\Support;

/**
 * Product photos: real photos dropped into database/seeders/product-images/
 * (named after the product, see the README there) win; a generated
 * placeholder is only the fallback when no photo exists.
 */
class ProductImage
{
    public const PHOTO_DIR = 'database/seeders/product-images';

    /**
     * Real photo files for a product name, gallery order: <slug>.<ext>,
     * <slug>-2.<ext>, <slug>-3.<ext> ...
     *
     * @return list<string> absolute paths (empty when none supplied)
     */
    public static function photos(string $name): array
    {
        $slug = \Illuminate\Support\Str::slug($name);

        $found = [];

        for ($n = 1; $n <= 8; $n++) {
            $base = base_path(self::PHOTO_DIR.'/'.$slug.($n === 1 ? '' : '-'.$n));

            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                if (is_file("$base.$ext")) {
                    $found[] = "$base.$ext";

                    break;
                }
            }
        }

        return $found;
    }

    public static function placeholder(string $path, string $text, int $w = 800, int $h = 1000): void
    {
        $img = imagecreatetruecolor($w, $h);

        imagefill($img, 0, 0, imagecolorallocate($img, 0x4A, 0x19, 0x42));

        imagefilledrectangle($img, 0, $h - (int) ($h * 0.16), $w, $h, imagecolorallocate($img, 0xE8, 0xB8, 0x4B));

        $white = imagecolorallocate($img, 255, 255, 255);
        $plum = imagecolorallocate($img, 0x4A, 0x19, 0x42);

        $font = '/usr/share/fonts/TTF/DejaVuSansMNerdFontPropo-Bold.ttf';

        $label = 'Classy Fashion Hub';
        $words = explode(' ', $text);

        if (is_file($font)) {
            $size = (int) ($w * 0.0425);

            $tb = imagettfbbox($size, 0, $font, $text);
            $tw = $tb[2] - $tb[0];

            if ($tw > $w - 80) {
                $size = (int) ($size * ($w - 80) / $tw);
            }

            $y = (int) ($h * 0.43);

            foreach (array_chunk($words, 2) as $line) {
                $line = implode(' ', $line);
                $tb = imagettfbbox($size, 0, $font, $line);
                imagettftext($img, $size, 0, (int) (($w - ($tb[2] - $tb[0])) / 2), $y, $white, $font, $line);
                $y += $size + 24;
            }

            $tb = imagettfbbox(24, 0, $font, $label);
            imagettftext($img, 24, 0, (int) (($w - ($tb[2] - $tb[0])) / 2), $h - 70, $plum, $font, $label);
        } else {
            imagestring($img, 5, 60, (int) ($h * 0.45), substr($text, 0, 40), $white);
            imagestring($img, 5, 60, (int) ($h * 0.45) + 50, $label, $white);
        }

        imagepng($img, $path);

        imagedestroy($img);
    }
}
