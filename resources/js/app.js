import { createApp } from 'vue';
import ObservationCockpit from './components/ObservationCockpit.vue';
import SurfaceTreeBrowser from './components/surface-tree/SurfaceTreeBrowser.vue';

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

const scheduleFilamentSidebarDefaults = () => {
    requestAnimationFrame(configureFilamentSidebarDefaults);
};

const initializeApp = () => {
    scheduleFilamentSidebarDefaults();
    mountObservationCockpit();
    mountSurfaceTreeBrowser();
};

document.addEventListener('alpine:init', scheduleFilamentSidebarDefaults);
document.addEventListener('alpine:initialized', scheduleFilamentSidebarDefaults);
document.addEventListener('livewire:navigated', initializeApp);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApp);
} else {
    initializeApp();
}
