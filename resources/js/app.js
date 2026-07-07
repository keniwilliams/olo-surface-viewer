import { createApp } from 'vue';
import ObservationCockpit from './components/ObservationCockpit.vue';
import SurfaceTreeBrowser from './components/surface-tree/SurfaceTreeBrowser.vue';
import SurfaceTreeFilterSelect from './components/surface-tree/SurfaceTreeFilterSelect.vue';
import SurfaceTreeSidebar from './components/surface-tree/SurfaceTreeSidebar.vue';

const desktopSidebarBreakpoint = 1024;

const configureFilamentSidebarDefaults = () => {
    const sidebar = window.Alpine?.store?.('sidebar');

    if (!sidebar || sidebar.__oloResizeDefaultsApplied) {
        return;
    }

    sidebar.__oloResizeDefaultsApplied = true;

    sidebar.resizeObserver?.disconnect?.();

    sidebar.setUpResizeObserver = function () {
        this.resizeObserver?.disconnect?.();
        this.resizeObserver = null;
    };

    if (localStorage.getItem('isOpen') !== null) {
        return;
    }

    const shouldOpenSidebar = window.innerWidth >= desktopSidebarBreakpoint;

    sidebar.isOpen = shouldOpenSidebar;
    sidebar.isOpenDesktop = shouldOpenSidebar;
};

const mountObservationCockpit = () => {
    const cockpit = document.getElementById('olo-observation-cockpit');

    if (!cockpit || cockpit.__oloObservationCockpitMounted) {
        return;
    }

    cockpit.__oloObservationCockpitMounted = true;

    createApp(ObservationCockpit, {
        organsUrl: cockpit.dataset.organsUrl,
        activityUrl: cockpit.dataset.activityUrl,
    }).mount(cockpit);
};

const mountSurfaceTreeBrowser = () => {
    const surfaceTree = document.getElementById('surface-tree-browser');

    if (!surfaceTree || surfaceTree.__oloSurfaceTreeMounted) {
        return;
    }

    surfaceTree.__oloSurfaceTreeMounted = true;

    createApp(SurfaceTreeBrowser).mount(surfaceTree);
};

const mountSurfaceTreeFilterSelect = () => {
    const filterSelect = document.getElementById('surface-tree-filter-select');

    if (!filterSelect || filterSelect.__oloSurfaceTreeFilterSelectMounted) {
        return;
    }

    filterSelect.__oloSurfaceTreeFilterSelectMounted = true;

    createApp(SurfaceTreeFilterSelect).mount(filterSelect);
};

const mountSurfaceTreeSidebar = () => {
    const sidebarTree = document.getElementById('surface-tree-sidebar');

    if (!sidebarTree || sidebarTree.__oloSurfaceTreeSidebarMounted) {
        return;
    }

    sidebarTree.__oloSurfaceTreeSidebarMounted = true;

    createApp(SurfaceTreeSidebar, {
        rootsUrl: sidebarTree.dataset.rootsUrl,
        childrenUrlTemplate: sidebarTree.dataset.childrenUrlTemplate,
    }).mount(sidebarTree);
};

const scheduleFilamentSidebarDefaults = () => {
    requestAnimationFrame(configureFilamentSidebarDefaults);
};

const surfaceTreeFlyoutOpenClass = 'surface-tree-flyout-open';
let surfaceTreeFlyoutDismissed = false;

const closeSurfaceTreeFlyout = () => {
    surfaceTreeFlyoutDismissed = true;
    document.documentElement.classList.remove(surfaceTreeFlyoutOpenClass);
};

const openSurfaceTreeFlyout = () => {
    surfaceTreeFlyoutDismissed = false;
    document.documentElement.classList.add(surfaceTreeFlyoutOpenClass);
};

// Clicking the nav item is a normal link (Filament/Livewire treats it as a
// `wire:navigate` navigation even when it points at the current page), so we
// don't fight that click. Instead, every time the surface tree page finishes
// loading (fresh load or SPA navigation) with the sidebar collapsed, we treat
// that as the trigger to show the flyout automatically.
const syncSurfaceTreeFlyout = () => {
    const sidebar = window.Alpine?.store?.('sidebar');
    const flyout = document.getElementById('surface-tree-flyout');
    const isDesktop = window.innerWidth >= desktopSidebarBreakpoint;

    if (flyout && isDesktop && !sidebar?.isOpen && !surfaceTreeFlyoutDismissed) {
        openSurfaceTreeFlyout();

        return;
    }

    document.documentElement.classList.remove(surfaceTreeFlyoutOpenClass);
};

const initSurfaceTreeFlyout = () => {
    if (document.__oloSurfaceTreeFlyoutInitialized) {
        return;
    }

    document.__oloSurfaceTreeFlyoutInitialized = true;

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-surface-tree-flyout-close]')) {
            return;
        }

        closeSurfaceTreeFlyout();
    });

    document.addEventListener('click', (event) => {
        if (!document.documentElement.classList.contains(surfaceTreeFlyoutOpenClass)) {
            return;
        }

        if (event.target.closest('#surface-tree-flyout') || event.target.closest('[data-surface-tree-nav-item]')) {
            return;
        }

        closeSurfaceTreeFlyout();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSurfaceTreeFlyout();
        }
    });
};

const initializeApp = () => {
    scheduleFilamentSidebarDefaults();
    mountObservationCockpit();
    mountSurfaceTreeBrowser();
    mountSurfaceTreeFilterSelect();
    mountSurfaceTreeSidebar();
    initSurfaceTreeFlyout();
    surfaceTreeFlyoutDismissed = false;
    syncSurfaceTreeFlyout();
};

document.addEventListener('alpine:init', scheduleFilamentSidebarDefaults);
document.addEventListener('alpine:initialized', scheduleFilamentSidebarDefaults);
document.addEventListener('livewire:navigated', initializeApp);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApp);
} else {
    initializeApp();
}
