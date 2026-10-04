# Product photos

Drop the shop's real product photos in this folder. Anything found here is
used instead of the generated placeholder:

- by the seeder when the catalogue is first created, and
- by `php artisan classy:import-photos` on an existing database (also run
  on every Render boot, so photos survive redeploys). Run `classy:repair-images`
  too if the storage disk was wiped.

**Naming:** the product name in lowercase with dashes, plus `.jpg`, `.jpeg`,
`.png` or `.webp`. Extra gallery shots add `-2`, `-3` ... (up to 8).
Portrait 4:5 (e.g. 800x1000 px), under about 500 KB each, keeps the shop fast on
mobile data.

Use photos you own (e.g. taken of Classy Fashion Hub's own stock) or have
written permission to use. Do not copy product photos from Jumia, Kikuu or other
shops: they belong to those sellers.

| Product | File name |
|---|---|
| Classic White Formal Shirt | `classic-white-formal-shirt.jpg` |
| Ankara Print Casual Shirt | `ankara-print-casual-shirt.jpg` |
| Plain Polo T-Shirt | `plain-polo-t-shirt.jpg` |
| Kitenge Print Shirt | `kitenge-print-shirt.jpg` |
| Denim Jacket | `denim-jacket.jpg` |
| Hooded Sweatshirt | `hooded-sweatshirt.jpg` |
| Fleece Hoodie | `fleece-hoodie.jpg` |
| Gomesi Traditional Dress | `gomesi-traditional-dress.jpg` |
| Kitenge Wrap Dress | `kitenge-wrap-dress.jpg` |
| Casual Midi Dress | `casual-midi-dress.jpg` |
| Canvas Sneakers | `canvas-sneakers.jpg` |
| Leather School Shoes | `leather-school-shoes.jpg` |
| Rubber Sandals | `rubber-sandals.jpg` |
| Student Backpack | `student-backpack.jpg` |
| Leather Belt | `leather-belt.jpg` |
| Baseball Cap | `baseball-cap.jpg` |
| Wool Beanie Hat | `wool-beanie-hat.jpg` |
| Canvas Tote Bag | `canvas-tote-bag.jpg` |
| Cotton Socks 3-Pack | `cotton-socks-3-pack.jpg` |
