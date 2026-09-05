@props(['name' => '', 'class' => 'h-5 w-5'])

@php
    // Maps the app's icon names to Heroicons Outline (24x24) icons.
    $map = [
        'dashboard' => 'squares-2x2',
        'clients' => 'users',
        'users' => 'users',
        'products' => 'cube',
        'box' => 'cube',
        'sales' => 'shopping-cart',
        'cart' => 'shopping-cart',
        'inventory' => 'archive-box',
        'warehouse' => 'archive-box',
        'reports' => 'chart-bar',
        'chart' => 'chart-bar',
        'settings' => 'cog-6-tooth',
        'cog' => 'cog-6-tooth',
        'menu' => 'bars-3',
        'close' => 'x-mark',
        'x-mark' => 'x-mark',
        'sun' => 'sun',
        'moon' => 'moon',
        'globe' => 'globe-americas',
        'logout' => 'arrow-right-start-on-rectangle',
        'plus' => 'plus',
        'search' => 'magnifying-glass',
        'filter' => 'funnel',
        'check' => 'check',
        'undo' => 'arrow-uturn-left',
        'alert' => 'exclamation-triangle',
        'info' => 'information-circle',
        'trash' => 'trash',
        'pencil' => 'pencil-square',
        'eye' => 'eye',
        'download' => 'arrow-down-tray',
        'chevron-down' => 'chevron-down',
        'chevron-left' => 'chevron-left',
        'chevron-right' => 'chevron-right',
        'arrow-left' => 'arrow-left',
        'arrow-down' => 'arrow-down',
        'arrow-up' => 'arrow-up',
        'receipt' => 'receipt-percent',
        'print' => 'printer',
        'calendar' => 'calendar',
        'coins' => 'banknotes',
        'audit' => 'clock',
    ];

    $heroName = null;

    if ($name !== '') {
        if (str_starts_with($name, 'heroicon-')) {
            $heroName = $name;
        } elseif (str_starts_with($name, 'fluentui-')) {
            // Render via the custom FluentUI set (config/blade-icons.php).
            $heroName = $name;
        } elseif (isset($map[$name])) {
            $heroName = 'heroicon-o-'.$map[$name];
        } else {
            // Pass-through: allow referencing any Heroicons Outline icon by name.
            $heroName = 'heroicon-o-'.$name;
        }
    }

    $extra = $attributes->getAttributes();

    $render = null;

    if ($heroName) {
        try {
            $render = svg($heroName, $class, $extra)->toHtml();
        } catch (\Throwable $e) {
            $render = null;
        }
    }
@endphp

@if ($render)
    {!! $render !!}
@endif
