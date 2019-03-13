<?php

# incspf exec
# incspf listdir
# incspf getDirSizeInfo

namespace SPF\rsync\media;

function getItems()
{

    $dir = 'media';
    $items = array();

    if (is_dir($dir . '/catalog/category')) {
        $items[] = 'catalog/category';
    }
    if (is_dir($dir . '/catalog/product')) {
        $items[] = 'catalog/product';
    }

    $items = array_merge($items, \SPF\listdir($dir));

    $sortList = array();
    $standardItems = array('catalog/category', 'catalog/product', 'wysiwyg');
    $typesPositions = array('standard' => 1, 'additional' => 2, 'file' => 3);
    $i = 0;
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (in_array($item, $standardItems)) {
            $type = 'standard';
        } elseif (is_dir($path)) {
            $type = 'additional';
        } else {
            $type = 'file';
        }
        $sizeInfo = is_dir($path)
            ? \SPF\getDirSizeInfo(
                $path, 0.2,
                'media/catalog/product/cache'
            )
            : ['bytes' => filesize($path), 'wasTimeout' => false];
        $sortList[] = array(
            'name' => $item,
            'type' => $type,
            'size' => $sizeInfo['bytes'],
            'isFullSize' => !$sizeInfo['wasTimeout'],
            'position' => $typesPositions[$type],
            'listPosition' => $i++
        );
    }

    usort($sortList, function ($A, $B) {
        $a = $A['position'];
        $b = $B['position'];
        if ($a === $b) {
            $a = $A['listPosition'];
            $b = $B['listPosition'];
        }
        return $a === $b ? 0 : ($a < $b ? -1 : 1);
    });

    return $sortList;

}
