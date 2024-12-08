<?php
session_start();
include 'sql/db_config.php'; // Include the database connection

// Fetch the search query from the URL
$search_query = isset($_GET['query']) ? $_GET['query'] : '';

// Fetch game and CD key details, filtered by search query if present
$catalog_items = [];
$sql = "
    SELECT 
        g.cover_art, 
        g.game_title,
        g.game_platform, 
        g.genre, 
        COALESCE(MIN(ck.price), ' N/A') AS price,
        ck.key_id AS cdkey_id,
        CASE 
            WHEN ck.status = 'available' THEN 'available'
            ELSE 'sold out'
        END AS status
    FROM 
        games g
    LEFT JOIN 
        (
            SELECT 
                game_id, 
                key_id, 
                price, 
                status 
            FROM 
                cd_keys 
            WHERE 
                status = 'available' 
            ORDER BY game_id, key_id DESC
        ) ck 
    ON 
        g.game_id = ck.game_id
    GROUP BY g.game_id
";

if (!empty($search_query)) {
    $sql .= " AND (g.game_title LIKE ? OR g.game_platform LIKE ? OR g.genre LIKE ?)";
}

$sql .= " ORDER BY g.game_title ASC";

$stmt = $conn->prepare($sql);

if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $catalog_items[] = $row;
    }
}

$stmt->close();

// Include the header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/catalog.css">
    <title>Game Catalog</title>
</head>
<body>
    <div class="main-container">
        <main>
            <h1>Game Catalog</h1>

            <?php if (!empty($search_query)): ?>
                <p>Search Results for: <strong><?= htmlspecialchars($search_query); ?></strong></p>
            <?php endif; ?>

            <div class="products-container">
                <?php if (!empty($catalog_items)): ?>
                    <?php foreach ($catalog_items as $item): ?>
                        <div class="product-card">
                            <img src="images/<?= htmlspecialchars($item['cover_art']); ?>" alt="<?= htmlspecialchars($item['game_title']); ?>">
                            <h3><?= htmlspecialchars($item['game_title']); ?></h3>
                            <p><strong>Platform:</strong> <?= htmlspecialchars($item['game_platform']); ?></p>
                            <p><strong>Genre:</strong> <?= htmlspecialchars($item['genre']); ?></p>
                            <p class="price">
                                <strong>Price:</strong> 
                                <?= $item['price'] !== 'N/A' ? "$" . htmlspecialchars($item['price']) : 'Not Available'; ?>
                            </p>
                            <p class="stock">
                                <strong>Stock:</strong> 
                                <?= $item['status'] === 'available' ? 'In Stock' : 'Sold Out'; ?>
                            </p>

                            <form method="POST" action="add_to_cart.php">
                                <input type="hidden" name="cdkey_id" value="<?= htmlspecialchars($item['cdkey_id']); ?>">
                                <button type="submit" class="buy-now-btn" <?= $item['status'] !== 'available' ? 'disabled' : ''; ?>>
                                    <?= $item['status'] === 'available' ? 'Buy Now' : 'Out of Stock'; ?>
                                </button>
                            </form>

                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No items available in the catalog!</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; // Include the footer ?>
</body>
</html>
