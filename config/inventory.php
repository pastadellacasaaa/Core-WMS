<?php

return [
    'snapshot_path' => env('WMS_INVENTORY_SNAPSHOT_PATH') ?: storage_path('app/inventory/inventory.xlsx'),
    'worksheet' => env('WMS_INVENTORY_WORKSHEET', 'INVENTORY'),
    'token' => env('WMS_INVENTORY_API_TOKEN', ''),
    'page_size' => 5000,
];
