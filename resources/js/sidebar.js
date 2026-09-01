export default function sidebar() {
    return {
        sidebarOpen: false,
        collapsed: false,

        init() {
            this.collapsed = localStorage.getItem('sidebarCollapsed') === '1';
        },

        toggleCollapsed() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebarCollapsed', this.collapsed ? '1' : '0');
        },
    };
}