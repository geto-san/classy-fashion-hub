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
- The gateway sets Paid; staff cannot mark a mobile-money order Paid by hand.
  A paid order cancelled by staff is **not refunded automatically**: the
  system flags it and the refund is made in the Flutterwave dashboard.
- Sandbox vs live is `FLUTTERWAVE_SANDBOX`; test keys with `false` (or live
  keys with `true`) leave the method hidden.

## Cut, in agreed order
1. **Luganda locale** — English only (report 10.9 lists it as future work).
2. **Live payments** — sandbox as above.
3. **Proven e-mail delivery** — customer status e-mails, low-stock e-mail and
   password reset are built, but `MAIL_MAILER=log` until real SMTP is set
   (`docs/DEPLOY.md`), so none has been seen arriving.

## Simplifications to know
- **Costs are illustrative** (60% of price, seeded). Enter real supplier
  costs per product for true profit figures. Each sale keeps the cost it had
  at the time; sales made before that was introduced were back-filled with
  the cost on the day of migration.
- **Footwear sizes** (EU 38–45) come from the catalogue seeder, which skips an
  already-seeded database. On an existing site re-create the two shoe products
  with a size option in admin, or reseed a fresh database.
- **Workers can still see a product's cost** by opening the product edit page
  (they need it to manage stock). They do not see profit reports or profit on
  orders. Remove product-edit from the Worker role if that matters to the owner.
- **Delivery partner** is a free-text name on the order (no rider directory,
  phone number or per-rider history).
- **Not measured**: usability with real users, phone/weak-network behaviour,
  load. **Backups** are manual scripts with no recorded restore drill.
- **Delivery instructions** live on the shipping address only. If a
  customer reuses the billing address for shipping, no instructions field
  is offered. Overlong input (>500 chars) is stopped client-side and by
  the column limit, not by a friendly 422 (core checkout validator has
  no hook for extra fields).
- **Completed → Dispatched auto-map**: core marks fully shipped orders
  “completed”; we record “dispatched”. Digital/downloadable orders would
  also show Dispatched — fine for a fashion shop, wrong for digital goods.
- **Prices in tests vs display**: API `min_price` once showed decimals;
  fixed via UGX `currency_position` (UGX 150,000).
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

- **AI assistant (report 5.1, first stage):** rule-based recommendations,
  shop chat and description filler work with no keys; an optional
  free-tier LLM (Groq default, any OpenAI-compatible endpoint) polishes
  open questions. Chat is throttled (30/min) plus a daily cap (200).
  True learned recommendations need order history volume first.

## Small core touches (documented for upgrade reviews)
- `Order` model: 4 status codes/labels (no extension seam exists). All other
  order behaviour lives in `ClassyFashion\Models\Sales\Order` (a subclass).
- `Cart`: delivery_instructions allow-list (+1 key); `OrderAddressResource` (+1 key).
- `tailwind.config.js`: primary token → plum (shop assets rebuilt).
- `bootstrap/providers.php`, `config/concord.php`, root `composer.json`:
  ClassyFashion package wiring. Exact diff against stock Bagisto: compare
  `store/packages/Webkul/...` with the same files in the v2.4.12 tag.
- The admin order list is **not** edited: `ClassyFashion\DataGrids\OrderDataGrid`
  is bound over it so it can show the report statuses.
