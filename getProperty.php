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

// 🔹 Get average price
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


// ✅ Apply filters

// City filter
if ($city) {
    $sql .= " AND properties.city = '$city'";
}

// ✅ Apply category — check for budget, luxury, or family-friendly
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

// ✅ Explicit budget filter
if ($budget === "low") {
    $sql .= " AND properties.price_per_night <= $avgPrice";
} elseif ($budget === "high") {
    $sql .= " AND properties.price_per_night > $avgPrice";
}

// ✅ Apply explicit max price filter
if ($price) {
    $sql .= " AND properties.price_per_night <= $price";
}

// ✅ Facilities filters
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

// 🔹 Liked properties filter 
if (isset($_GET['liked_ids'])) {
    $likedIds = $_GET['liked_ids'];
    $likedIdsArray = explode(',', $likedIds);
    $likedIdsArray = array_filter($likedIdsArray, fn($id) => is_numeric($id));
    if (!empty($likedIdsArray)) {
        $likedIdsString = implode(',', $likedIdsArray);
        $sql .= " AND properties.property_id IN ($likedIdsString)";
    }
}

if (!empty($ratingValues)) {
    $escapedRatings = implode(",", $ratingValues);
    $sql .= " GROUP BY properties.property_id HAVING AVG(reviews.rating) IN ($escapedRatings)";
} else {
    $sql .= " GROUP BY properties.property_id";
}


// 🔍 Run query
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Query failed: " . mysqli_error($conn),
    ]);
    exit();
}

// ✅ Output response
$properties = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'average_price' => $avgPrice,
    'properties' => $properties
]);
?>
