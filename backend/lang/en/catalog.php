<?php

return [
    'barcode_invalid' => 'The :attribute field only accepts printable ASCII characters without spaces, up to 64.',
    'barcode_check_digit' => 'The :attribute field has the length of a GTIN and its check digit is not valid.',
    'category_hierarchy_cycle' => [
        'title' => 'The change would close a cycle in the category hierarchy.',
        'detail' => 'Category :parent is :category or one of its subcategories and cannot be its parent.',
    ],
    'category_hierarchy_too_deep' => [
        'title' => 'The category hierarchy would exceed the maximum depth.',
        'detail' => 'The category hierarchy accepts up to :max levels.',
    ],
];
