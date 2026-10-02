# Known Limitations & Next Steps

Honest list of what was cut, stubbed or simplified — and the report's
future work they map to.

## Payments: sandbox only
- Mobile money runs against **Flutterwave test keys**. No live merchant
  approval was sought, so no real UGX moves. Production switch: live keys
  in `.env` + `FLUTTERWAVE_SANDBOX=false` + a public webhook URL
  (localhost cannot receive Flutterwave webhooks; use ngrok/Render and set
  the webhook URL + secret hash in the Flutterwave dashboard).
- Sandbox demo without webhooks: the status page re-verifies server-side
  (“I Have Approved — Check Status”) and finalizes identically.
- `pending` → `paid` is allowed for the gateway path; manual flow stays
  pending → confirmed → paid.

## Cut, in agreed order
1. **Luganda locale** — English only (report 10.9 lists it as future work).
2. **Live payments** — sandbox as above.
3. **Email notifications** — dashboard alerts only: threshold widget,
   `stock.low` audit entries, admin new-order notification rows.

## Simplifications to know
- **Costs are illustrative** (60% of price, seeded). Enter real supplier
  costs per product for true profit figures.
- **Delivery instructions** live on the shipping address only. If a
  customer reuses the billing address for shipping, no instructions field
  is offered. Overlong input (>500 chars) is stopped client-side and by
  the column limit, not by a friendly 422 (core checkout validator has
  no hook for extra fields).
- **Completed → Dispatched auto-map**: core marks fully shipped orders
  “completed”; we record “dispatched”. Digital/downloadable orders would
  also show Dispatched — fine for a fashion shop, wrong for digital goods.
- **Prices in tests vs display**: API `min_price` once showed decimals;
  fixed via UGX `currency_position` (USh 150,000).
- **Dependency advisories**: `composer audit` flags league/commonmark
  (transitive, pre-existing). No advisories on added packages.
- **Render hosting**: free tier has no MySQL; deploy needs an external
  MySQL (or VPS). Local demo is the verified path.

## Report future work (out of scope, by design)
- **AI recommendations** (5.1): needs purchase history + a recommender;
  hook point is the category/product API + a new package.
- **Virtual fitting preview** (5.2): needs AR/size-tech spike first.
- **Multi-seller marketplace** (5.3): needs seller accounts, per-seller
  inventory/settlement and marketplace roles — the package structure
  (`ClassyFashion`) is where they would live; Bagisto marketplace
  add-ons are paid, verify licensing before citing.

- **Cloudinary evaluated and rejected:** uploads work but the only
  Laravel-12-compatible Flysystem adapter (codebar-ag v12.9) returns
  broken reads (SDK signature mismatch) and 404ing delivery URLs, while
  v13 requires Laravel 13. Media persistence is handled by
  `classy:repair-images` instead (see DEPLOY.md).

## Small core touches (documented for upgrade reviews)
- `Order` model: 4 status codes/labels (no extension seam exists).
- `Cart`: delivery_instructions allow-list (+1 key); `OrderAddressResource` (+1 key).
- `tailwind.config.js`: primary token → plum (shop assets rebuilt).
- `bootstrap/providers.php`, `config/concord.php`, root `composer.json`:
  ClassyFashion package wiring. Full diff: `git log --stat`.
