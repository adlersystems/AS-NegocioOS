export default function languageSwitcher() {
    return {
        switching: false,

        setLocale(locale) {
            if (this.switching) {
                return;
            }

            this.switching = true;

            fetch(`/language/${locale}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            })
                .then(() => {
                    window.location.reload();
                })
                .finally(() => {
                    this.switching = false;
                });
        },
    };
}