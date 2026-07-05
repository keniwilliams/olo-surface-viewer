<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Surface Tree - Surface Viewer</title>

        @vite(['resources/css/app.css', 'resources/js/surface-tree.ts'])
    </head>
    <body>
        <main id="surface-tree-browser"></main>
    </body>
</html>
