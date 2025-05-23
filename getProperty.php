<?php
include 'helpers/connection.php';

// Get and sanitize filter parameters
$city = isset($_GET['city']) ? mysqli_real_escape_string($conn, $_GET['city']) : null;
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : null;
$budget = isset($_GET['budget']) ? $_GET['budget'] : null;
$rating = isset($_GET['rating']) ? $_GET['rating'] : null;
$ratingValues = $rating ? explode(",", $rating) : [];
$price = isset($_GET['price']) ? intval($_GET['price']) : null;

$facilities = ['wifi', 'pool', 'pets', 'parking', 'family'];

// Get average price
$avgQuery = "SELECT AVG(price_per_night) AS avg_price FROM properties";
$avgResult = mysqli_query($conn, $avgQuery);
$avgData = mysqli_fetch_assoc($avgResult);
$avgPrice = (float)$avgData['avg_price'];

// Base query
$sql = "SELECT properties.*, categories.category_name, 
        AVG(reviews.rating) AS average_rating
        FROM properties 
        JOIN categories ON properties.category_id = categories.category_id 
        JOIN vendors ON properties.vendor_id = vendors.vendor_id 
        LEFT JOIN reviews ON properties.property_id = reviews.property_id
        WHERE vendors.account_status = 'active'";

// City filter
if ($city) {
    $sql .= " AND properties.city = '$city'";
}

// Category filter
if ($category) {
    if (strtolower($category) === "budget") {
        $sql .= " AND properties.price_per_night <= $avgPrice";
    } elseif (strtolower($category) === "luxury") {
        $sql .= " AND properties.price_per_night > $avgPrice";
    } elseif (strtolower($category) === "family-friendly") {
        $sql .= " AND properties.crib = 'Crib'";
    } else {
        $sql .= " AND categories.category_name = '$category'";
    }
}

// Budget filter
if ($budget === "low") {
    $sql .= " AND properties.price_per_night <= $avgPrice";
} elseif ($budget === "high") {
    $sql .= " AND properties.price_per_night > $avgPrice";
}

// Price range filter
if ($price) {
    $buffer = round($price * 0.15); // 15% buffer
    $minPrice = max(0, $price - $buffer);
    $maxPrice = $price + $buffer;
    $sql .= " AND properties.price_per_night BETWEEN $minPrice AND $maxPrice";
}

// Facilities filters
foreach ($facilities as $facility) {
    if (isset($_GET[$facility]) && $_GET[$facility] === "1") {
        if ($facility === "wifi") {
            $sql .= " AND properties.wifi = 'Wi-fi'";
        } elseif ($facility === "pool") {
            $sql .= " AND properties.pool = 'Swimming pool'";
        } elseif ($facility === "pets") {
            $sql .= " AND properties.pet_friendly = 'Pet Friendly'";
        } elseif ($facility === "parking") {
            $sql .= " AND properties.parking = 'Parking Available'";
        } elseif ($facility === "family") {
            $sql .= " AND properties.crib = 'Crib'";
        }
    }
}

// Liked properties filter
if (isset($_GET['liked_ids'])) {
    $likedIds = $_GET['liked_ids'];
    $likedIdsArray = explode(',', $likedIds);
    $likedIdsArray = array_filter($likedIdsArray, fn($id) => is_numeric($id));
    if (!empty($likedIdsArray)) {
        $likedIdsString = implode(',', $likedIdsArray);
        $sql .= " AND properties.property_id IN ($likedIdsString)";
    }
}

// Group by property ID (required for AVG aggregation)
$sql .= " GROUP BY properties.property_id";

// Apply rating filter with HAVING only if ratings filter exists
if (!empty($ratingValues)) {
    $escapedRatings = implode(",", array_map('intval', $ratingValues));
    $sql .= " HAVING ROUND(AVG(reviews.rating)) IN ($escapedRatings)";
}

// Sort by newest registered properties first
$sql .= " ORDER BY properties.registered_at DESC";

// Run query
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Query failed: " . mysqli_error($conn),
    ]);
    exit();
}

// Format output
$properties = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['rating'] = round($row['average_rating'], 1); // Add rounded rating field
    unset($row['average_rating']); // Remove original average_rating field
    $properties[] = $row;
}

// Output response
echo json_encode([
    'success' => true,
    'average_price' => $avgPrice,
    'properties' => $properties
]);
?>
