@vite('resources/css/surface-tree-sidebar.css')

<div
    id="surface-tree-sidebar"
    data-surface-tree-sidebar
    data-roots-url="{{ route('surface-tree.nodes.roots') }}"
    data-children-url-template="{{ route('surface-tree.nodes.children', ['nodeKey' => '__NODE_KEY__']) }}"
></div>
