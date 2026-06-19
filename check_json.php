<?php
$json_str = '';

$values = json_decode($json_str, true);

print_r($values);
die;

// function yieldInnerValues(array $items): Generator {
//     foreach ($items as $item) {
//         // Предположим, что $item — это объект, а 'target_array' — нужный ключ
//         foreach ($item["fields"] as $value) {
//             yield $value;
//         }
//     }
// }

// foreach (yieldInnerValues($values) as $value) {
//     if ($value['fieldName'] == 'changeRequired') {
//         echo $value['default']  . PHP_EOL;
//         break;
//     }
// }

function yieldInnerValuesWithKeys(array $items): Generator {
    foreach ($items as $item) {
        if (is_array($item) && isset($item['fields']) && is_array($item['fields'])) {
            foreach ($item["fields"] as $index => $value) {
                // Возвращаем в качестве ключа имя поля (fieldName), а в качестве значения — весь массив
                $key = $value['fieldName'] ?? $index;
                yield $key => $value;
            }
        }
    }
}
foreach (yieldInnerValuesWithKeys($values) as $fieldName => $value) {
    if ($fieldName === 'diagram') {
        echo $value['default'] . PHP_EOL;
        break;
    }
}


$searchFor = "docTitle";
$foundParentKey = null;

// array_walk_recursive обходит только листовые (конечные) значения
// array_walk_recursive($values, function($value, $key) use ($searchFor, &$foundParentKey) {
//     if ($value === $searchFor) {
//         $foundParentKey = $key; // Запоминаем имя ключа ("target_key")
//     }
// });

// echo "Ключ найден: " . $foundParentKey; // target_key

