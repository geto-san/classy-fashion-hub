# User Guide — Classy Fashion Hub Online Shop

Live locally at `http://localhost:8000` (shop) and `http://localhost:8000/admin`.

## Customer

### Browse and search
- Open the shop. Categories: Mens (`/mens`), Womens (`/womens`), Footwear
  (`/footwear` — under Mens). Use search for names like “jacket”.
- Prices are fixed in Uganda Shillings (UGX) — no bargaining.

### Order
1. Open a product, pick **size and colour**, add to cart.
2. Register (`/customer/register`) or log in
   (local/demo only: `customer@classy.local / customer123`; on the live site
   register your own account).
3. At checkout, fill the billing address. For delivery, fill the shipping
   address: location (address, city), contact phone, and **delivery
   instructions** (e.g. gate, landmark — shipping form only).
4. Choose shipping (Flat Rate) and payment:
   - **Cash on Delivery** — order is created immediately as Pending.
   - **Mobile Money (MTN / Airtel)** — choose network, enter a Ugandan
     mobile money number (e.g. `0772000000`), then **approve the prompt on
     your phone**. The order appears only after the network confirms payment.
     If the prompt times out and you were charged, quote the reference shown
     to the shop; do not pay twice.

### Pay with mobile money (demo)
1. Sandbox keys must be in `store/.env` (see README / .env.example).
2. **MTN (self-service sandbox):** sign up at momodeveloper.mtn.com with
   just an email → subscribe to the Collection product → create an API
   user → copy the subscription key, user ID and API key into
   `MTN_SUBSCRIPTION_KEY/_API_USER_ID/_API_KEY`. No business documents.
3. **No account at all:** set `MOMO_TILL_NUMBER` to the shop's Till or
   line. Customers pay by phone, submit the SMS transaction ID, and staff
   confirm it in Payments after checking the MTN/Airtel app.
4. Check out with Mobile Money → approve on the phone (or use the Till
   flow) → use **“I Have Approved — Check Status”**. The order shows up
   with status **Paid** only after confirmation. Returning from any
   payment page without approving creates **no order** — your cart is kept.

### Track an order
- Account → Orders shows every order with its live status:
  Pending → Confirmed → Paid → Processing → Dispatched → Delivered.
- Click an order for items, totals, delivery details and an **Order
  progress** list showing when each status was reached. You will also get an
  e-mail at each step once the shop's mail is set up.
- You can cancel an order yourself only while it is Pending or Confirmed;
  after payment, contact the shop.

### Shop assistant (`/assistant`)
- Ask about products (“green dresses under 100000”), prices, sizes,
  delivery, MTN/Airtel payments, returns, or “where is my order?”
  (logged in). Works without any setup; with a free Groq key in `.env`
  (`AI_LLM_*`, see `.env.example`) it also handles open questions.

## Worker (demo: `worker@classy.local / worker123`; the owner creates real worker accounts)

- Dashboard, catalogue, sales orders, customer list, sales reports.
- **Confirm** new orders (Pending → Confirmed) with the “Mark as …” buttons
  on the order view.
  - *Mobile money* orders arrive already **Paid** (only the gateway can set
    that). *Bank/cash-in-shop* orders: mark **Paid** by hand when you have
    the money — it is recorded with your name.
  - *Cash on delivery*: Confirmed → **Processing** directly; pack, ship, mark
    **Dispatched**, then **Delivered** — the cash received is recorded when you
    mark it Delivered.
  - Invoice and Shipment buttons appear only once an order is Paid or
    Processing (COD: Confirmed or Processing).
  - **Rider / delivery partner:** type the name in the *Save Rider* box on
    the order page; every change is recorded in the audit trail.
  - Footwear is chosen by EU size (38–45) and colour like clothing.
- Update stock on the product edit page (inventories). Every change is
  logged with your name. Low-stock products (≤ 5 units) appear red on the
  dashboard threshold widget.
- You cannot open Settings, Roles/Users, Configuration, the Audit Log or the
  payments list (401), you do not see cost or profit figures, and you cannot
  delete products — deactivate them instead (Status toggle).

## Admin (locally `admin@example.com / admin123`; on the live site the password is the one you set in `SEED_ADMIN_PASSWORD` or the random one printed once in the deploy log — change it after signing in)

- Everything the worker can do, plus: users and roles
  (`/admin/settings/roles`), taxes, configuration
  (Configuration → Catalog → Inventory → threshold; Sales → Payment Methods
  → Mobile Money display settings), reports with date ranges and CSV export
  (`/admin/reporting/sales`), dashboard statistics.
- Order view shows per-item and total **profit** (cost is saved on each sale
  when it happens; seeded costs are 60% of price demo data — enter real costs
  on each product), delivery instructions, and a **Payment record** (the
  gateway reference, or who took the cash and when).
- **Mobile Money Payments** (menu): every payment attempt with its gateway
  reference. A red banner means money was received but no order exists —
  create the order by hand or refund, quoting the reference.
- **Audit Log** (Reporting): who changed stock, order statuses, payments,
  staff accounts and roles, with filters and CSV export.
- Cancelling a **Paid** mobile-money order puts the stock back and shows a
  reminder to refund the customer from the MTN MoMo app (refunds are
  not automatic).
