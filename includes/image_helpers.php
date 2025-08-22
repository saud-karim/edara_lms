<?php
/**
 * Image Helper Functions
 * Functions to handle image paths and validation
 */

/**
 * Check if an image exists and return the correct path
 * @param string $imagePath The image path from database
 * @return array Contains 'exists' boolean and 'path' string
 */
function checkImagePath($imagePath) {
    if (empty($imagePath)) {
        return ['exists' => false, 'path' => ''];
    }
    
    // Clean the path
    $cleanPath = trim($imagePath);
    
    // Try different path variations
    $pathsToTry = [
        $cleanPath,                          // Original path
        './' . $cleanPath,                   // With ./
        '../' . $cleanPath,                  // With ../
        str_replace('../', '', $cleanPath),  // Remove ../
        str_replace('./', '', $cleanPath)    // Remove ./
    ];
    
    foreach ($pathsToTry as $path) {
        if (file_exists($path)) {
            return [
                'exists' => true, 
                'path' => $cleanPath // Return original path for web display
            ];
        }
    }
    
    return [
        'exists' => false, 
        'path' => $cleanPath
    ];
}

/**
 * Get the web-accessible URL for an image
 * @param string $imagePath The image path from database
 * @return string The web URL
 */
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '';
    }
    
    // Ensure path starts correctly for web access
    $cleanPath = ltrim($imagePath, './');
    
    // If it doesn't start with assests/uploads/, add it
    if (!str_starts_with($cleanPath, 'assests/uploads/')) {
        if (str_contains($cleanPath, 'uploads/')) {
            // Extract uploads part if it's buried in the path
            $cleanPath = 'assests/uploads/' . substr($cleanPath, strpos($cleanPath, 'uploads/') + 8);
        }
    }
    
    return $cleanPath;
}

/**
 * Generate a thumbnail-safe image tag with error handling
 * @param string $imagePath The image path
 * @param string $alt Alt text
 * @param string $class CSS classes
 * @param array $attributes Additional attributes
 * @return string HTML img tag
 */
function generateImageTag($imagePath, $alt = '', $class = '', $attributes = []) {
    if (empty($imagePath)) {
        return generateNoImageDiv('لا توجد صورة');
    }
    
    $imageCheck = checkImagePath($imagePath);
    $webUrl = getImageUrl($imagePath);
    
    if (!$imageCheck['exists']) {
        return generateNoImageDiv('الصورة غير موجودة', $imagePath);
    }
    
    $attrs = [
        'src' => htmlspecialchars($webUrl),
        'alt' => htmlspecialchars($alt),
        'class' => $class,
        'onerror' => "this.style.display='none'; this.nextElementSibling.style.display='block';"
    ];
    
    // Merge additional attributes
    $attrs = array_merge($attrs, $attributes);
    
    $attrString = '';
    foreach ($attrs as $key => $value) {
        if (!empty($value)) {
            $attrString .= $key . '="' . $value . '" ';
        }
    }
    
    $errorDiv = generateNoImageDiv('خطأ في تحميل الصورة', $imagePath, 'display: none;');
    
    return '<img ' . trim($attrString) . '>' . $errorDiv;
}

/**
 * Generate a "no image" div
 * @param string $message Message to display
 * @param string $path Optional path to show
 * @param string $style Optional inline styles
 * @return string HTML div
 */
function generateNoImageDiv($message, $path = '', $style = '') {
    $pathHtml = '';
    if (!empty($path)) {
        $pathHtml = '<br><small class="text-muted">المسار: ' . htmlspecialchars($path) . '</small>';
    }
    
    $styleAttr = !empty($style) ? ' style="' . $style . '"' : '';
    
    return '<div class="no-image"' . $styleAttr . '>
                <i class="glyphicon glyphicon-picture"></i>
                <p>' . htmlspecialchars($message) . '</p>
                ' . $pathHtml . '
            </div>';
}

/**
 * Validate and fix image paths in database
 * @param PDO $conn Database connection
 * @return array Results of the operation
 */
function validateAndFixImagePaths($conn) {
    $results = [
        'personal_licenses' => ['checked' => 0, 'fixed' => 0, 'errors' => []],
        'vehicle_licenses' => ['checked' => 0, 'fixed' => 0, 'errors' => []]
    ];
    
    // Check personal licenses
    try {
        $stmt = $conn->query("SELECT license_id, front_image_path, back_image_path FROM personal_licenses WHERE is_active = 1");
        while ($row = $stmt->fetch()) {
            $results['personal_licenses']['checked']++;
            
            $frontFixed = false;
            $backFixed = false;
            
            // Check front image
            if (!empty($row['front_image_path'])) {
                $frontCheck = checkImagePath($row['front_image_path']);
                if (!$frontCheck['exists']) {
                    $results['personal_licenses']['errors'][] = "License ID {$row['license_id']}: Front image not found - {$row['front_image_path']}";
                }
            }
            
            // Check back image
            if (!empty($row['back_image_path'])) {
                $backCheck = checkImagePath($row['back_image_path']);
                if (!$backCheck['exists']) {
                    $results['personal_licenses']['errors'][] = "License ID {$row['license_id']}: Back image not found - {$row['back_image_path']}";
                }
            }
        }
    } catch (Exception $e) {
        $results['personal_licenses']['errors'][] = "Database error: " . $e->getMessage();
    }
    
    // Check vehicle licenses
    try {
        $stmt = $conn->query("SELECT license_id, front_image_path, back_image_path FROM vehicle_licenses WHERE is_active = 1");
        while ($row = $stmt->fetch()) {
            $results['vehicle_licenses']['checked']++;
            
            // Check front image
            if (!empty($row['front_image_path'])) {
                $frontCheck = checkImagePath($row['front_image_path']);
                if (!$frontCheck['exists']) {
                    $results['vehicle_licenses']['errors'][] = "License ID {$row['license_id']}: Front image not found - {$row['front_image_path']}";
                }
            }
            
            // Check back image
            if (!empty($row['back_image_path'])) {
                $backCheck = checkImagePath($row['back_image_path']);
                if (!$backCheck['exists']) {
                    $results['vehicle_licenses']['errors'][] = "License ID {$row['license_id']}: Back image not found - {$row['back_image_path']}";
                }
            }
        }
    } catch (Exception $e) {
        $results['vehicle_licenses']['errors'][] = "Database error: " . $e->getMessage();
    }
    
    return $results;
}
?> 