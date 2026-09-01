export default function saleForm(config = {}) {
    const products = config.products ?? [];

    const productById = (id) => products.find((p) => String(p.id) === String(id));

    const clampQuantity = (value, max) => {
        const maxVal = Math.max(1, Number(max) || 1);

        return Math.min(Math.max(1, value), maxVal);
    };

    return {
        products,
        iva: Number(config.iva ?? 0),
        items: [],

        init() {
            const seed = config.items?.length ? config.items : [{}];

            this.items = seed.map((item) => this.buildItem(item));
        },

        buildItem(data = {}) {
            const product = data.product_id ? productById(data.product_id) : null;
            const quantity = numberOrDefault(data.quantity, 1);
            const unitPrice = product ? Number(product.price) : numberOrDefault(data.unit_price, 0);

            return {
                product_id: data.product_id ?? '',
                quantity,
                unit_price: unitPrice,
                max_stock: (product ? Number(product.stock) : 0) + (Number(data.original_quantity ?? 0) || 0),
                line_total: round(unitPrice * quantity),
            };
        },

        productChanged(index) {
            const item = this.items[index];
            const product = productById(item.product_id);

            if (! product) {
                item.unit_price = 0;
                item.max_stock = 0;
                item.line_total = 0;

                return;
            }

            item.unit_price = Number(product.price);
            item.max_stock = Number(product.stock) + (Number(item.original_quantity ?? 0) || 0);
            item.quantity = clampQuantity(item.quantity, item.max_stock);
            item.line_total = round(item.unit_price * item.quantity);
        },

        quantityChanged(index) {
            const item = this.items[index];

            item.quantity = clampQuantity(item.quantity, item.max_stock);
            item.line_total = round(item.unit_price * item.quantity);
        },

        addItem() {
            const last = this.items[this.items.length - 1];

            if (last && ! last.product_id) {
                return;
            }

            this.items.push(this.buildItem());
        },

        removeItem(index) {
            if (this.items.length <= 1) {
                this.items[0] = this.buildItem();

                return;
            }

            this.items.splice(index, 1);
        },

        productLabel(product) {
            const stock = `${product.stock}`;

            return product.sku ? `${product.name} (${product.sku}) – ${stock}` : `${product.name} – ${stock}`;
        },

        formatMoney(value) {
            return 'Q ' + Number(value ?? 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        get subtotal() {
            return this.items.reduce((sum, item) => sum + (Number(item.line_total) || 0), 0);
        },

        get tax() {
            return round(this.subtotal * (this.iva / 100));
        },

        get total() {
            return round(this.subtotal + this.tax);
        },
    };
}

function numberOrDefault(value, fallback) {
    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function round(value) {
    return Math.round(value * 100) / 100;
}