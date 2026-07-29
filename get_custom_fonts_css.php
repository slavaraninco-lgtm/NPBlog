<?php
require_once __DIR__ . '/security_bootstrap.php';
// Функция для генерации CSS с @font-face правилами для пользовательских шрифтов
function getCustomFontsCss() {
    $fontsDir = getDataPath('fonts/');
    $css = '';
    
    if (is_dir($fontsDir)) {
        $files = scandir($fontsDir);
        
        // Resolve data directory name for correct web path
        $dataDir = getDataPath();
        $dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            
            // Supported font formats
            if (in_array($ext, ['ttf', 'otf', 'woff', 'woff2'])) {
                $fontName = pathinfo($file, PATHINFO_FILENAME);
                
                // Construct the correct absolute web URL
                $fontWebUrl = '/' . $dirName . '/fonts/' . $file;
                
                // Determine font format
                $format = 'truetype';
                if ($ext === 'woff') $format = 'woff';
                else if ($ext === 'woff2') $format = 'woff2';
                else if ($ext === 'otf') $format = 'opentype';
                
                $css .= "\n        @font-face {\n";
                $css .= "            font-family: '$fontName';\n";
                $css .= "            src: url('$fontWebUrl') format('$format');\n";
                $css .= "        }\n";
            }
        }
    }
    
    return $css;
}
?>
