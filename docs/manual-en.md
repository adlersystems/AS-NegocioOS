# User manual — AS-NegocioOS

Business management system (mini-ERP): clients, products, sales, inventory, reports,
settings and audit trail.

---

## Table of contents

1. [Introduction](#1-introduction)
2. [Getting started](#2-getting-started)
3. [The interface](#3-the-interface)
4. [Dashboard](#4-dashboard)
5. [Clients](#5-clients)
6. [Products](#6-products)
7. [Sales](#7-sales)
8. [Inventory](#8-inventory)
9. [Reports](#9-reports)
10. [Settings](#10-settings)
11. [Audit trail](#11-audit-trail)
12. [Users](#12-users)
13. [Roles and permissions](#13-roles-and-permissions)
14. [Frequently asked questions](#14-frequently-asked-questions)

---

## 1. Introduction

**AS-NegocioOS** is a business management system for small and medium businesses that lets you
manage clients, products, invoiced sales and inventory in one place.

Access is controlled by **roles**: each user only sees the modules and actions their role
allows. The available roles are:

- **Administrator**: full access to every module.
- **Seller**: creates sales, manages clients, and views products, sales and reports.
- **Manager**: manages products, inventory and reports; views and records sales, and manages
  clients.

See the [roles and permissions table](#13-roles-and-permissions) for the full detail.

---

## 2. Getting started

### 2.1 Signing in

1. Open the system address in your browser (e.g. `https://adlersystems.com/as-negocioos`).
2. If you are not signed in, you will see the **Sign in** screen.
3. Enter your **email address** and **password**.
4. Click the **Sign in** button.

You will be taken to your [Dashboard](#4-dashboard) with your name and role.

> The system is private: a user must first be created by an administrator to be able to sign in.

### 2.2 Forgot your password?

1. On the sign-in screen, click **Forgot your password?**.
2. Enter the email of your account and click the button to send the reset link.
3. Check your email inbox and open the link you received.
4. Enter your new password and confirm it.

> If you do not receive the email, ask an administrator to verify the system's email
> configuration.

---

## 3. The interface

Once signed in, the interface has two main areas:

### 3.1 Sidebar (menu)

Shows the modules you have access to according to your role. Click any option to open it.
The modules are:

`Dashboard` · `Clients` · `Products` · `Sales` · `Inventory` · `Reports` · `Audit trail` ·
`Users` · `Settings`

- The menu **collapses** with the double-arrow button (on large screens) to free up space.
- On small screens (mobile/phones), the menu is hidden and opens with the **menu** button
  (three lines) at the top.

### 3.2 Top bar

From here you can:

- **Switch the language** (globe 🌐): toggle between **English** and **Español**. The change
  applies to the whole app during your session.
- **Switch the theme** (sun/moon): toggle between **light** and **dark** mode.
- **User menu**: shows your name and email, and includes the **Sign out** option.

### 3.3 Signing out

1. Click your name (top-right corner).
2. Select **Sign out**.

---

## 4. Dashboard

The dashboard is the initial screen after signing in. It summarizes your business activity:

### 4.1 Core metrics

Four cards with global totals:

- **Clients**: total registered clients.
- **Products**: total products in the catalog.
- **Sales**: total registered sales.
- **Receivables**: outstanding balance of unpaid sales (only those with an assigned client).

### 4.2 KPIs

- **Today's revenue**: total sold today.
- **Month revenue**: total sold in the current month.
- **Average sale**: average amount per sale.
- **Inventory value**: total cost of current stock (production cost × stock).

### 4.3 Inventory alerts

Shows alerts about products that need attention, with direct access to the product list:

- **Out of stock**: products with no stock.
- **Low stock**: products at or below their minimum stock.
- **Expiring soon**: products with an upcoming expiration date.

### 4.4 Charts

Shown when sales have been recorded:

- **Monthly sales** (last 6 months).
- **Sales by seller**.
- **Top-selling products**.
- **Revenue trend** (last 12 months).

### 4.5 Top client and recent sales

- **Top client**: the client with the highest total purchased.
- **Recent sales**: the last 8 sales, with invoice number, client, seller, amount and
  recency. Click **View all** to go to [Sales](#7-sales).

---

## 5. Clients

The **Clients** module lets you keep a complete record of your clients and their purchase
history.

### 5.1 Creating a client

1. Go to **Clients** and click the **New client** (or **Create**) button.
2. Fill out the form:
   - **Name** *(required)*.
   - **NIT** *(optional)*.
   - **Email** *(optional)*.
   - **Phone** *(optional)*.
   - **Address** *(optional)*.
   - **Preferred language**: English or Español.
   - **Notes** *(optional)*: any additional information.
3. Click **Save**.

### 5.2 Viewing a client

In the list, click the client's name (or the **View** button) to open their profile with:
- Contact information.
- Purchase summary (count and total).
- Outstanding balance from their unpaid sales.
- Their sales history.

### 5.3 Editing a client

1. In the list, find the client and click **Edit**.
2. Change the needed fields.
3. Click **Save changes**.

### 5.4 Deleting a client

1. In the list, click **Delete**.
2. Confirm in the confirmation dialog by clicking **Yes, delete**.

> Deleting a client does not remove their past sales; those sales simply keep no client
> assigned.

### 5.5 Searching and exporting

- Use the **search** box to find clients by name or NIT.
- Click **Export PDF** or **Export Excel** to download the current list.

---

## 6. Products

The **Products** module manages your catalog, stock and expiration dates.

### 6.1 Creating a product

1. Go to **Products** and click **New product** (or **Create**).
2. Fill out the form:
   - **Name** *(required)*.
   - **SKU** *(optional)*: internal reference code.
   - **Production cost** *(required)*: unit cost of the product.
   - **Sale price** *(required)*.
   - **Stock** *(required)*: current quantity in inventory.
   - **Min stock** *(required)*: the level below which a low-stock alert is shown.
   - **Expiration date** *(optional)*: for perishable products.
   - **Active product**: if checked, the product can be sold. Uncheck it to disable its sale
     without deleting it.
   - **Description** *(optional)*.
3. Click **Save**.

> Each product's margin (difference between sale price and cost) is calculated automatically
> and shown in the list and in reports.

### 6.2 Viewing a product

Click the product (or **View**) to see its data, its inventory movements and its share in
sales.

### 6.3 Editing and deleting

- **Edit**: change the needed fields and click **Save changes**.
- **Delete**: click **Delete** and confirm with **Yes, delete**.

> If a product has already been sold, it cannot be deleted. Instead, uncheck **Active product**
> to stop offering it.

### 6.4 Stock and alerts

Each product in the list shows a status badge:

- **In stock**: stock above the minimum.
- **Low stock**: stock at or below the minimum.
- **Out of stock**: no stock.
- **Expiring soon**: upcoming expiration date.

The dashboard summarizes these alerts as a group.

### 6.5 Searching and exporting

- Use the **search** box to find products by name, SKU or description.
- Use the filters to view **all**, **out of stock**, **low stock** or **expiring soon**.
- Click **Export PDF** or **Export Excel** to download the list.

---

## 7. Sales

The **Sales** module lets you record invoices with dynamic items and automatic stock updates.

### 7.1 Creating a sale

1. Go to **Sales** and click **New sale** (or **Create**).
2. Fill out the general data:
   - **Client** *(optional)*: select a client or leave it empty for a sale without a client
     invoice.
   - **Seller** *(required)*: the seller. Defaults to you.
   - **Notes** *(optional)*.
3. Add the sale items:
   - Click **Add item**.
   - Select the **product**; the unit price and the line total are calculated automatically.
   - Enter the **quantity**; the system validates it does not exceed available stock.
   - Repeat for each product. Click the trash icon to remove an item.
4. Review the automatic totals: **subtotal**, **tax** (configured percentage) and **total**.
5. Click **Save**.

When saved:

- The **invoice number** is generated automatically.
- The **stock** of each product is deducted automatically.
- An **outbound inventory movement** is recorded.
- The sale is created with a **unpaid** status.

### 7.2 Viewing an invoice

From the sales list, click the invoice number (or **View**). The screen shows:

- Invoice number, date, client and seller.
- Product list, quantities, unit prices and totals.
- Subtotal, tax and total.
- Notes (if any).
- Status: **Paid** or **Unpaid**.

### 7.3 Marking as paid / unpaid

Sales recorded on credit remain **unpaid**. When the client pays:

1. Open the sale.
2. Click the **Mark as paid** button.
3. The status changes to **Paid**.

If it was a mistake, you can mark it back as **unpaid** with the same button.

> Only the **Administrator** and **Manager** roles can change the payment status.

### 7.4 Editing a sale (Administrator only)

1. Open the sale and click **Edit**.
2. Adjust the data or the items (products, quantities). The system recalculates totals and
   **reconciles stock** against the original quantities.
3. Click **Save changes**.

### 7.5 Voiding / deleting a sale (Administrator only)

1. Open the sale and click **Delete**.
2. Confirm with **Yes, delete**.

When a sale is deleted, the stock of its products is **restored** automatically.

### 7.6 Exporting

- **Invoice PDF**: inside a sale, click **Export PDF** to download the invoice ready to print
  or send.
- **List PDF / Excel**: on the **Sales** page, with search and filters applied, click
  **Export PDF** or **Export Excel** to download the list.

### 7.7 Searching and filtering

The list supports:

- **Search** by invoice number, client or notes.
- **Filter by seller** and by **date range**.

---

## 8. Inventory

The **Inventory** module records every product entry and exit, and adjusts stock
automatically.

> Available to the **Administrator** and **Manager** roles.

### 8.1 Recording a movement

1. Go to **Inventory** and click **New movement** (or **Create**).
2. Fill out the form:
   - **Product** *(required)*.
   - **Type** *(required)*: **In** (increases stock) or **Out** (decreases stock).
   - **Quantity** *(required)*: number of units.
   - **Reason** *(required)*: the reason for the movement (e.g. supplier purchase, shrinkage,
     internal use).
   - **Reference** *(optional)*: document number or reference (e.g. supplier invoice).
3. Click **Save**.

The product's stock is updated automatically and the movement is recorded in the list with
its date, type, in/out amount and resulting balance.

> Sales also generate outbound movements automatically. When you edit or void a sale, the
> corresponding corrective movements are recorded.

### 8.2 Viewing history and exporting

- Filter by **product** and by **date range**.
- Click **Export PDF** or **Export Excel** to download the movement report.

---

## 9. Reports

The **Reports** module lets you analyze your business and export information. Available to
the **Administrator** and **Manager** roles.

### 9.1 Report types

There are four tabs:

- **Sales**: sales listing with client, seller, dates, amounts and payment status.
- **Inventory**: per-product summary: entries, exits and net balance.
- **Clients**: per client: number of sales and total purchased.
- **Products**: per product: units sold, revenue and margin.

**Global metrics** are shown at the top: total sales, products, clients and inventory value.

### 9.2 Filters

Depending on the report type, you can filter by:

- **From date / to date** (all types).
- **Client** (sales and clients).
- **Product** (inventory and products).
- **Seller** and **payment status** (sales).

1. Select the report type in the tabs.
2. Apply the filters you need.
3. Click **Apply**. The report updates instantly.

### 9.3 Exporting

Once filters are applied:

- **Export PDF**: downloads the report as a PDF (for printing or archiving).
- **Export Excel**: downloads the report as an Excel file (for data analysis).

The downloaded file respects the applied filters.

---

## 10. Settings

The **Settings** module (**Administrator** only) defines your company data and system
preferences.

### 10.1 Company details

- **Company name** *(required)*: shown in the top bar and in documents.
- **Tagline**: short phrase under the name.
- **Logo**: company logo image (PNG, JPG, WEBP or SVG formats). Shown in the sidebar and on
  the sign-in screen.

### 10.2 Contact details

The data shown on invoices and reports:

- **NIT**
- **Email**
- **Phone**
- **Address**

### 10.3 Preferences

- **Currency**: Quetzales (GTQ) or Dollars (USD). Defines the symbol used in amounts.
- **Tax (%)**: percentage applied to sales (default 12).
- **Default language**: the language the system opens in for new users.

> Changing the default language does not affect the language each user chose on their account.

To save your changes, click **Save changes** at the end of the form.

---

## 11. Audit trail

The **Audit trail** module (**Administrator** only) records every create, update or delete
performed in the system, so you always know who did what and when.

### 11.1 The log

Each entry shows:

- **Date** and time.
- **User** who performed the action.
- **Action**: Created, Updated or Deleted.
- **Model** (e.g. Client, Product, Sale).
- **Record** affected (with a direct link if it still exists).
- **IP** address it was performed from.

### 11.2 Filtering

You can filter by:

- **Model** (all or a specific one).
- **Action** (created, updated, deleted).
- **User**.
- **Date range**.

Click **Apply** to filter, or **Reset** to clear the filters.

### 11.3 Viewing the detail

Click **View** on any entry to see the full detail, including the **fields that changed**
(old value → new value) when the action was an update.

> Audit records cannot be deleted from the application.

---

## 12. Users

The **Users** module (**Administrator** only) manages access to the system.

### 12.1 Creating a user

1. Go to **Users** and click **New user** (or **Create**).
2. Fill out the form:
   - **Name** *(required)*.
   - **Email** *(required)*: used to sign in.
   - **Role** *(required)*: Administrator, Seller or Manager.
   - **Language** *(required)*: default interface language for this user.
   - **Password** *(required when creating)*.
3. Click **Save**.

> The system has no public registration: all users are created here.

### 12.2 Editing a user

1. In the list, find the user and click **Edit**.
2. Change the name, email, role, language or password.
3. To keep the current password, leave the field **blank** (it is only replaced if you type a
   new one).
4. Click **Save changes**.

### 12.3 Deleting a user

1. Click **Delete** on the corresponding user.
2. Confirm with **Yes, delete**.

> Caution: deleting a user removes their access. Their past sales keep their name as the
> historical seller.

---

## 13. Roles and permissions

| Capability | Administrator | Seller | Manager |
|-----------|:-----------:|:---------:|:---------:|
| View dashboard | ✔ | ✔ | ✔ |
| Clients (create/view/edit/delete) | ✔ | ✔ | ✔ |
| Clients (export PDF/Excel) | ✔ | ✔ | ✔ |
| Products (view) | ✔ | ✔ | ✔ |
| Products (create/edit/delete) | ✔ | ✘ | ✔ |
| Sales (create) | ✔ | ✔ | ✘ |
| Sales (view list and invoice) | ✔ | ✔ | ✔ |
| Sales (mark paid/unpaid) | ✔ | ✘ | ✔ |
| Sales (edit/void) | ✔ | ✘ | ✘ |
| Inventory (movements and export) | ✔ | ✘ | ✔ |
| Reports (view and export) | ✔ | ✘ | ✔ |
| Settings | ✔ | ✘ | ✘ |
| Audit trail | ✔ | ✘ | ✘ |
| Users (CRUD) | ✔ | ✘ | ✘ |

---

## 14. Frequently asked questions

**I don't see some modules in the menu.**
That is normal: the menu adapts to your role. If you need access to another module, an
administrator must change your role in **Users**.

**How do I change the app language?**
Click the **globe** icon in the top bar and choose English or Español. Your choice applies
immediately. You can also assign a per-user language (from **Users** as administrator) and a
default language in **Settings**.

**How do I enable dark mode?**
Click the **sun/moon** icon in the top bar. The preference is saved in your browser.

**I forgot my password.**
From the sign-in screen, click **Forgot your password?** and follow the link you receive by
email.

**Does an unpaid sale mean I must collect from the client?**
Yes. When a sale is recorded, its initial status is **unpaid**. Mark it as **paid** when the
client pays. Unpaid sales with a client add up in **receivables** on the dashboard.

**What happens if I try to sell more units of a product that is out of stock?**
The system validates the quantity against the available stock and will not let you sell more
than you have. First register an **inventory entry** to restock.

**Why can't I delete a product?**
If a product has already been sold, it cannot be deleted. Instead, uncheck it as **active
product** to stop selling it.

**Do the reports respect my filters when exported?**
Yes. The PDF and Excel files are generated with exactly the same filters you see on screen.

---

© AS-NegocioOS. Internal use document.