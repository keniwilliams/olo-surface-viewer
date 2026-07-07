@vite('resources/css/surface-tree-sidebar.css')

<div class="surface-tree-flyout" id="surface-tree-flyout">
    <div class="surface-tree-flyout__header">
        <span class="surface-tree-flyout__title">Surface Tree</span>
        <button type="button" class="surface-tree-flyout__close" data-surface-tree-flyout-close aria-label="Close surface tree">&times;</button>
    </div>

    <div
        id="surface-tree-sidebar"
        data-surface-tree-sidebar
        data-roots-url="{{ route('surface-tree.nodes.roots') }}"
        data-children-url-template="{{ route('surface-tree.nodes.children', ['nodeKey' => '__NODE_KEY__']) }}"
    ></div>
</div>
