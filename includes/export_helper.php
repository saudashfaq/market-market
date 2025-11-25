<?php
/**
 * Generic Export Helper Functions
 * Supports CSV, Excel, and PDF exports
 */

/**
 * Export data to CSV format
 */
function exportToCSV($data, $filename, $headers = null) {
    if (empty($data)) {
        return false;
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    if ($headers) {
        fputcsv($output, $headers);
    } else {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Export data to Word format
 */
function exportToWord($data, $filename, $title = 'Report', $headers = null) {
    if (empty($data)) {
        return false;
    }
    
    // Set headers for Word document
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="' . $filename . '.doc"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $recordCount = count($data);
    
    // Start Word document HTML
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; }';
    echo 'h1 { color: #333; font-size: 24px; margin-bottom: 10px; }';
    echo 'p { margin: 5px 0; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    echo 'th { background-color: #4472C4; color: white; padding: 10px; text-align: left; border: 1px solid #000; font-weight: bold; }';
    echo 'td { padding: 8px; border: 1px solid #000; }';
    echo 'tr:nth-child(even) { background-color: #F2F2F2; }';
    echo '</style>';
    echo '</head><body>';
    
    // Document header
    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p><strong>Total Records:</strong> ' . $recordCount . '</p>';
    echo '<hr>';
    
    // Table
    echo '<table border="1">';
    
    // Table Headers
    echo '<thead><tr>';
    if ($headers) {
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
    } else {
        foreach (array_keys($data[0]) as $key) {
            echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . '</th>';
        }
    }
    echo '</tr></thead>';
    
    // Table Data
    echo '<tbody>';
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            // Handle null values
            $cellValue = ($cell === null || $cell === '') ? '-' : $cell;
            echo '<td>' . htmlspecialchars($cellValue) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    
    echo '</body></html>';
    
    exit;
}

/**
 * Export data to PDF format
 */
function exportToPDF($data, $filename, $title = 'Report', $headers = null) {
    if (empty($data)) {
        return false;
    }
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html><html><head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #333; border-bottom: 2px solid #333; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo '@media print { body { margin: 0; } }';
    echo '</style></head><body>';
    
    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p><strong>Total Records:</strong> ' . count($data) . '</p>';
    
    echo '<table>';
    
    // Headers
    echo '<thead><tr>';
    if ($headers) {
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
    } else {
        foreach (array_keys($data[0]) as $key) {
            echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . '</th>';
        }
    }
    echo '</tr></thead>';
    
    // Data rows
    echo '<tbody>';
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    
    echo '<script>window.onload = function() { window.print(); };</script>';
    echo '</body></html>';
    
    exit;
}

/**
 * Generic export handler
 */
function exportData($data, $format, $filename, $title = 'Report', $headers = null) {
    if (empty($data)) {
        die('No data to export');
    }
    
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = $filename . '_' . date('Y-m-d_H-i-s');
    
    switch (strtolower($format)) {
        case 'csv':
            exportToCSV($data, $filename, $headers);
            break;
            
        case 'excel':
        case 'xls':
        case 'word':
        case 'doc':
            exportToWord($data, $filename, $title, $headers);
            break;
            
        case 'pdf':
            exportToPDF($data, $filename, $title, $headers);
            break;
            
        default:
            die('Unsupported export format: ' . $format);
    }
}

/**
 * Get export button HTML - Word Document
 */
function getExportButton($dataType, $title = 'Export', $extraParams = []) {
    // Get current URL without export parameter
    $currentUrl = $_SERVER['REQUEST_URI'];
    
    // Remove any existing export parameter
    $currentUrl = preg_replace('/[?&]export=[^&]*/', '', $currentUrl);
    $currentUrl = preg_replace('/[?&]type=[^&]*/', '', $currentUrl);
    
    $separator = strpos($currentUrl, '?') !== false ? '&' : '?';
    
    $extraQuery = '';
    if (!empty($extraParams)) {
        $extraQuery = '&' . http_build_query($extraParams);
    }
    
    $wordUrl = $currentUrl . $separator . 'export=word&type=' . $dataType . $extraQuery;
    
    return '
    <a href="' . htmlspecialchars($wordUrl) . '" 
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors inline-flex items-center gap-2 no-underline">
        <i class="fa fa-file-word"></i>
        <span>Export</span>
    </a>';
}

/**
 * Handle export request - call at top of page
 */
function handleExportRequest($data, $defaultTitle = 'Report', $headers = null) {
    if (isset($_GET['export']) && isset($_GET['type'])) {
        // Check if data is empty
        if (empty($data)) {
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>No Data</title></head><body>';
            echo '<h1>No Data Available</h1>';
            echo '<p>There is no data to export.</p>';
            echo '<p><a href="javascript:history.back()">Go Back</a></p>';
            echo '</body></html>';
            exit;
        }
        
        // Clear any output buffers before export
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $format = $_GET['export'];
        $type = $_GET['type'];
        $title = $_GET['title'] ?? $defaultTitle;
        $filename = $type . '_report';
        exportData($data, $format, $filename, $title, $headers);
        exit;
    }
}