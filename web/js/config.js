// ==========================================================
// KameraPH UI Kit  |  config.js
// This is the ONLY file you edit when addresses change
// (for example, when you upload to the web host).
// ==========================================================

const KPH = {
  // 1. Your own module's API address (no slash at the end)
  api: "http://localhost/kph_fulfillment_logistics/api",

  // 2. For testing only: the user ID sent with every request.
  //    The login token will replace this later.
  testUserId: "1",

  // 3. The web address of every module's pages (its "web" folder, no slash at the end).
  //    Use localhost while testing and the real address after uploading.
  modules: {
    accounts:    "http://localhost/kph_user_account_identity/web",      // Module 1  User Account and Identity
    sellers:     "http://localhost/kph_seller_verification/web",       // Module 2  Seller and Verification
    catalog:     "http://localhost/kph_catalog_specification/web",       // Module 3  Catalog and Specification
    engagement:  "http://localhost/kph_marketing_engagement/web",    // Module 4  Marketing and Engagement
    shopping:    "http://localhost/kph_shopping_promotions/web",      // Module 5  Shopping and Promotions
    orders:      "http://localhost/kph_order_payment/web",        // Module 6  Order and Payment
    fulfillment: "http://localhost/kph_fulfillment_logistics/web",   // Module 7  Fulfillment and Logistics
    shipping:    "http://localhost/kph_shipping_delivery/web",      // Module 8  Shipping and Delivery
    support:     "http://localhost/kph_post_purchase_support/web",       // Module 9  Post-Purchase Support
    admin:       "http://localhost/kph_administration_analytics/web"          // Module 10 Administration and Analytics
  },

  // 4. The header menu. The project leader decides which page belongs to which module.
  //    (These page names are examples.)
  nav: [
    { label: "Home",       module: "catalog",  page: "index.html" },
    { label: "Categories", module: "catalog",  page: "categories.html" },
    { label: "Deals",      module: "shopping", page: "deals.html" },
    { label: "About Us",   module: "catalog",  page: "about.html" },
    { label: "Contact",    module: "support",  page: "contact.html" }
  ],

  // 5. The header icons (also examples)
  links: {
    account:  { module: "accounts",   page: "account.html" },
    wishlist: { module: "engagement", page: "wishlist.html" },
    cart:     { module: "shopping",   page: "cart.html" },
    search:   { module: "catalog",    page: "search.html" }
  }
};
