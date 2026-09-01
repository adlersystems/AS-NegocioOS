export default function toast(options = {}) {
    return {
        active: [],
        defaultDuration: options.duration ?? 4000,

        init() {
            const el = this.$el;

            ['success', 'error', 'warning', 'info'].forEach((type) => {
                const suffix = type.charAt(0).toUpperCase() + type.slice(1);
                const value = el.dataset[`flash${suffix}`];

                if (value) {
                    this.push(type, value);
                }
            });

            window.showToast = (type, message) => this.push(type, message);
        },

        push(type, message) {
            const id = Date.now() + Math.random();
            const item = { id, type, message, show: false, leaving: false };

            this.active.push(item);

            setTimeout(() => {
                item.show = true;
            }, 20);

            setTimeout(() => this.close(id), this.defaultDuration);
        },

        success(message) {
            this.push('success', message);
        },

        error(message) {
            this.push('error', message);
        },

        warning(message) {
            this.push('warning', message);
        },

        info(message) {
            this.push('info', message);
        },

        close(id) {
            const item = this.active.find((t) => t.id === id);

            if (! item) {
                return;
            }

            item.show = false;
            item.leaving = true;

            setTimeout(() => {
                this.active = this.active.filter((t) => t.id !== id);
            }, 300);
        },
    };
}