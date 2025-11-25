<?php
/**
 * Generic Pagination Helper
 * Use this in any file to add pagination to tables
 */

/**
 * Generate pagination data
 * 
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param array $conditions WHERE conditions ['column' => 'value']
 * @param int $page Current page number
 * @param int $perPage Items per page
 * @param string $orderBy ORDER BY clause (e.g., 'created_at DESC')
 * @return array Pagination data
 */
function getPaginationData($pdo, $table, $conditions = [], $page = 1, $perPage = 10, $orderBy = 'id DESC', $paramName = 'pg') {
    // Build WHERE clause
    $whereClause = '';
    $params = [];
    
    if (!empty($conditions)) {
        $whereParts = [];
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle LIKE searches
                if (isset($value['like']) && !empty($value['like'])) {
                    $whereParts[] = "`$column` LIKE :$column";
                    $params[":$column"] = '%' . $value['like'] . '%';
                }
                // Handle IN conditions
                elseif (isset($value['in']) && !empty($value['in'])) {
                    $placeholders = [];
                    foreach ($value['in'] as $i => $val) {
                        $placeholder = ":{$column}_$i";
                        $placeholders[] = $placeholder;
                        $params[$placeholder] = $val;
                    }
                    $whereParts[] = "`$column` IN (" . implode(',', $placeholders) . ")";
                }
            } else {
                if (!empty($value)) {
                    $whereParts[] = "`$column` = :$column";
                    $params[":$column"] = $value;
                }
            }
        }
        if (!empty($whereParts)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM `$table`";
    if (!empty($whereClause)) {
        $countSql .= " $whereClause";
    }
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $totalPages = ceil($totalItems / $perPage);
    $offset = ($page - 1) * $perPage;
    
    // Get data
    $dataSql = "SELECT * FROM `$table`";
    if (!empty($whereClause)) {
        $dataSql .= " $whereClause";
    }
    $dataSql .= " ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
    
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'start_item' => $offset + 1,
            'end_item' => min($offset + $perPage, $totalItems)
        ]
    ];
}

/**
 * Render pagination HTML
 * 
 * @param array $pagination Pagination data from getPaginationData()
 * @param string $baseUrl Base URL for pagination links
 * @param array $extraParams Extra URL parameters to maintain
 * @return string HTML for pagination
 */
function renderPagination($pagination, $baseUrl, $extraParams = [], $paramName = 'pg') {
    if ($pagination['total_pages'] <= 1) {
        return '<div class="text-center text-gray-500 py-4">
                    <i class="fa-solid fa-list mr-2"></i>
                    All results displayed on this page
                </div>';
    }
    
    $html = '<div class="flex items-center justify-center space-x-2 py-4">';
    
    // First page button
    if ($pagination['current_page'] > 1) {
        $firstUrl = buildPaginationUrl($baseUrl, 1, $extraParams, $paramName);
        $html .= '<a href="' . $firstUrl . '" class="px-3 py-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="First Page">
                    <i class="fa-solid fa-angles-left"></i>
                  </a>';
    } else {
        $html .= '<span class="px-3 py-2 text-gray-300 cursor-not-allowed" title="First Page">
                    <i class="fa-solid fa-angles-left"></i>
                  </span>';
    }
    
    // Previous button
    if ($pagination['has_prev']) {
        $prevUrl = buildPaginationUrl($baseUrl, $pagination['prev_page'], $extraParams, $paramName);
        $html .= '<a href="' . $prevUrl . '" class="px-3 py-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Previous Page">
                    <i class="fa-solid fa-angle-left"></i>
                  </a>';
    } else {
        $html .= '<span class="px-3 py-2 text-gray-300 cursor-not-allowed" title="Previous Page">
                    <i class="fa-solid fa-angle-left"></i>
                  </span>';
    }
    
    // Page numbers
    $startPage = max(1, $pagination['current_page'] - 2);
    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    if ($startPage > 1) {
        $firstUrl = buildPaginationUrl($baseUrl, 1, $extraParams, $paramName);
        $html .= '<a href="' . $firstUrl . '" class="px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">1</a>';
        if ($startPage > 2) {
            $html .= '<span class="px-3 py-2 text-gray-400">...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<span class="px-3 py-2 bg-blue-600 text-white rounded-lg font-medium">' . $i . '</span>';
        } else {
            $pageUrl = buildPaginationUrl($baseUrl, $i, $extraParams, $paramName);
            $html .= '<a href="' . $pageUrl . '" class="px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">' . $i . '</a>';
        }
    }
    
    if ($endPage < $pagination['total_pages']) {
        if ($endPage < $pagination['total_pages'] - 1) {
            $html .= '<span class="px-3 py-2 text-gray-400">...</span>';
        }
        $lastUrl = buildPaginationUrl($baseUrl, $pagination['total_pages'], $extraParams, $paramName);
        $html .= '<a href="' . $lastUrl . '" class="px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">' . $pagination['total_pages'] . '</a>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $nextUrl = buildPaginationUrl($baseUrl, $pagination['next_page'], $extraParams, $paramName);
        $html .= '<a href="' . $nextUrl . '" class="px-3 py-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Next Page">
                    <i class="fa-solid fa-angle-right"></i>
                  </a>';
    } else {
        $html .= '<span class="px-3 py-2 text-gray-300 cursor-not-allowed" title="Next Page">
                    <i class="fa-solid fa-angle-right"></i>
                  </span>';
    }
    
    // Last page button
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $lastUrl = buildPaginationUrl($baseUrl, $pagination['total_pages'], $extraParams, $paramName);
        $html .= '<a href="' . $lastUrl . '" class="px-3 py-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Last Page">
                    <i class="fa-solid fa-angles-right"></i>
                  </a>';
    } else {
        $html .= '<span class="px-3 py-2 text-gray-300 cursor-not-allowed" title="Last Page">
                    <i class="fa-solid fa-angles-right"></i>
                  </span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Build pagination URL with parameters
 */
function buildPaginationUrl($baseUrl, $page, $extraParams = [], $paramName = 'pg') {
    $params = array_merge($extraParams, [$paramName => $page]);
    $queryString = http_build_query($params);
    return $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . $queryString;
}

/**
 * Get current page from URL
 */
function getCurrentPage($paramName = 'pg') {
    return max(1, intval($_GET[$paramName] ?? 1));
}

/**
 * Simple pagination for custom queries
 * Use when you need custom SQL queries
 */
function getCustomPaginationData($pdo, $sql, $countSql, $params = [], $page = 1, $perPage = 10, $paramName = 'pg') {
    // Get total count
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $totalPages = ceil($totalItems / $perPage);
    $offset = ($page - 1) * $perPage;
    
    // Add LIMIT to SQL
    $sql .= " LIMIT $perPage OFFSET $offset";
    
    // Get data
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'start_item' => $offset + 1,
            'end_item' => min($offset + $perPage, $totalItems)
        ]
    ];
}
?>