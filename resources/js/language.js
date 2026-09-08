export default function languageSwitcher() {
    return {
        switching: false,

setLocale(locale) {
        if (this.switching) {
            return;
        }

        this.switching = true;

        const baseUrl = document.querySelector('meta[name="app-url"]')?.content || '';
        fetch(`${baseUrl}/language/${locale}`, {
                method: 'POST',
                redirect: 'manual',
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