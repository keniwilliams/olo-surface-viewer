import { createApp } from 'vue';
import SurfaceTreeBrowser from './components/surface-tree/SurfaceTreeBrowser.vue';

type SurfaceTreeMountTarget = HTMLElement & {
    __oloSurfaceTreeMounted?: boolean;
};

const mountSurfaceTree = () => {
    const surfaceTree = document.getElementById('surface-tree-browser') as SurfaceTreeMountTarget | null;

    if (!surfaceTree || surfaceTree.__oloSurfaceTreeMounted) {
        return;
    }

    surfaceTree.__oloSurfaceTreeMounted = true;

    createApp(SurfaceTreeBrowser).mount(surfaceTree);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountSurfaceTree);
} else {
    mountSurfaceTree();
}
