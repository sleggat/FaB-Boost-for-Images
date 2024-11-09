<?php
/**
 * Generates responsive image HTML with srcset for optimized loading
 * 
 * @param string $url Image URL path
 * @param string $alt Alt text for the image
 * @param string $class Additional CSS classes
 * @param string|array $params Additional URL parameters
 * @param array $options Configuration options
 * @return string Generated HTML for responsive image
 * @throws Exception If image file is not found or invalid
 */
function fab_boost(
    string $url,
    string $alt = '',
    string $class = '',
    $params = '',
    array $options = []
) {
    // Default configuration
    $config = array_merge([
        'min_width' => 300,
        'max_width' => 4000,
        'step_size' => 100,
        'step_increment' => 150,
        'default_width' => 500,
        'lazy_load' => true,
        'webp_support' => true
    ], $options);

    // Validate input file
    $file_path = '.' . $url;
    if (!file_exists($file_path)) {
        throw new Exception("Image file not found: {$url}");
    }

    // Get image dimensions with error handling
    $size = @getimagesize($file_path);
    if ($size === false) {
        throw new Exception("Invalid image file: {$url}");
    }
    
    $width = $size[0] ?: 'auto';
    $height = $size[1] ?: 'auto';
    
    // Smart format detection
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    $format = match ($extension) {
        'gif' => '&amp;format=gif',
        'png' => '&amp;format=png',
        'webp' => '&amp;format=webp',
     'avif' =>  '&amp;format=avif',
        default => '&amp;format=jpg'
    };
    
    // Efficient cache busting
    $modified = filemtime($file_path);
    
    // Build base URL with caching
    $urlmodified = sprintf('%s%s?v=%d', gumlet_URL, $url, $modified);
    
    // Handle parameters more flexibly
    if (is_array($params)) {
        $params = http_build_query($params, '', '&amp;');
    }
    if (!empty($params)) {
        $urlmodified .= '&amp;' . ltrim($params, '&amp;');
    }
    
    // More efficient srcset generation using array
    $srcset_parts = [];
    $x = $config['min_width'];
    $i = 0;
    
    while ($x <= $config['max_width']) {
        if ($x > $width) {
            $srcset_parts[] = $urlmodified . $format . '&amp;w=' . $width . ' ' . $width . 'w';
            break;
        }
        $srcset_parts[] = $urlmodified . $format . '&amp;w=' . $x . ' ' . $x . 'w';
        $i += $config['step_increment'];
        $x += $config['step_size'] + $i;
    }
    
    // Build classes array
    $classes = [];
    if ($config['lazy_load']) {
        $classes[] = 'lazy';
    }
    if (!empty($class)) {
        $classes = array_merge($classes, array_filter(explode(' ', $class)));
    }
    $class_attribute = !empty($classes) ? 'class="' . implode(' ', array_unique($classes)) . '"' : '';
    
    // Build attributes array for cleaner HTML generation
    $img_attributes = array_filter([
        $class_attribute,
        'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"',
        'width="' . $width . '"',
        'height="' . $height . '"',
        'srcset="' . implode(', ', $srcset_parts) . '"',
        $config['lazy_load'] ? 'data-src="' . $urlmodified . $format . '&amp;w=' . $config['default_width'] . '"' : '',
        'src="' . $urlmodified . $format . '&amp;w=' . $config['default_width'] . '"'
    ]);

    // Generate WebP version if enabled
    if ($config['webp_support'] && $extension !== 'webp') {
        $webp_srcset_parts = array_map(function($srcset_part) {
            return str_replace($format, '&amp;format=webp', $srcset_part);
        }, $srcset_parts);
        
        return sprintf(
            '<picture>
                <source type="image/webp" srcset="%s">
                <img %s>
            </picture>',
            implode(', ', $webp_srcset_parts),
            implode(' ', $img_attributes)
        );
    }
    
    // Return standard img tag
    return '<img ' . implode(' ', $img_attributes) . '>';
}

// Example usage:
/*
$options = [
    'min_width' => 400,
    'max_width' => 2000,
    'webp_support' => true,
    'lazy_load' => true
];

echo fab_boost(
    '/assets/image.jpg',
    'My image description',
    'custom-class',
    ['quality' => 90],
    $options
);
*/
?>