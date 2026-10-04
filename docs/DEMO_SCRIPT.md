# Demo Script — six SMART objectives

Run on `http://localhost:8000` + `/admin`. Demo accounts (local only) in README.md.

## 1. Digital product catalogue
- **Show:** `/womens` (4 products), open Gomesi → size/colour selectors,
  fixed “UGX 150,000”, description, availability.
- **Say:** names, categories, sizes, colours, descriptions, prices and
  availability without visiting the shop (objective 1).
- **Screenshot:** product page with an open size dropdown + price.

## 2. Inventory and digital records
- **Show:** admin product edit → Inventories (quantities per variant);
  admin order view → **Order Profit** block; Reporting → Sales totals +
  CSV export.
- **Say:** dates, items, amounts and profits recorded digitally with
  basic reports (objective 2).
- **Screenshot:** sales report with the 3 demo orders; order profit block.

## 3. Online ordering workflow
- **Show:** as customer: add variant → cart → COD checkout → Account →
  Orders shows **Pending**; open it for items and totals.
- **Say:** registered customers select, submit, view and track orders
  (objective 3).
- **Screenshot:** order history with Pending badge.

## 4. Payment + delivery info
- **Show:** checkout payment step (Mobile Money MTN/Airtel beside COD);
  shipping form **Delivery Instructions** field; admin order view
  delivery block.
- **Say (sandbox):** approve on the phone → status page → order becomes
  **Paid** only after gateway confirmation; returns alone create nothing.
- **Screenshot:** mobile-money pending page; admin delivery block.

## 5. Role-based access
- **Show:** admin sees Settings/Users; worker login → those menu items
  gone; paste `/admin/settings/roles` as worker → **401**. Worker
  confirms an order (Pending → Confirmed) with “Mark as …” buttons.
- **Say:** admins vs workers, every stock/status change stamped with the
  user (objective 5). Admin → Reporting → **Audit Log** shows the entries;
  as worker the same page returns 401, and no profit figures are visible.
- **Screenshot:** worker 401 page; order status buttons.

## 6. Usability, reliability, security, performance
- **Show:** terminal run `php vendor/bin/pest tests/Feature/`
  (83 test cases: roles, checkout, statuses, order rules, audit, profit,
  payments, notifications, reports — quote the number from your actual run);
  phone view of the shop (responsive layout, thumb-sized
  buttons, readable prices).
- **Say:** tested end to end before submission (objective 6); passwords
  hashed, CSRF on, secrets in `.env` only, server-side permission checks.
- **Screenshot:** green test run; phone screenshot of a product page.
