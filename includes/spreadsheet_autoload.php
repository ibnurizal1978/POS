<?php
// PSR Log autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'Psr\\Log\\') === 0) {
        $path = __DIR__ . '/../vendor/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require $path;
        }
    }
});

// PhpSpreadsheet autoloader
spl_autoload_register(function ($class) {
    // Only handle PhpSpreadsheet classes
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet') !== 0) {
        return;
    }

    // Base directory for PhpSpreadsheet classes
    $base_dir = __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/';
    
    // Convert namespace to full file path
    $relative_class = substr($class, strlen('PhpOffice\\'));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Required core files
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IComparable.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Exception.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Exception.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Functions.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Calculation.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/DataType.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/DataValidation.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/DefaultValueBinder.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/IValueBinder.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/Cell.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Collection/Cells.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Collection/Memory.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Settings.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/Style.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/NumberFormat.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Shared/StringHelper.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/RichText/RichText.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/RichText/ITextElement.php';
require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/RichText/TextElement.php'; 