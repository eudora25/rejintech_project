<?php
// scripts/prefill_test.php

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = 'A77ila@'; // From docker-compose.yml
$db_name = 'jintech';
$db_port = 3306; // Default MariaDB/MySQL port

// Connect to the database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database connection successful!\n\n";

// 1. Truncate the table
echo "Truncating product_categories table...\n";
if ($conn->query("SET FOREIGN_KEY_CHECKS=0;") === TRUE && $conn->query("TRUNCATE TABLE product_categories;") === TRUE && $conn->query("SET FOREIGN_KEY_CHECKS=1;") === TRUE) {
    echo "Table truncated successfully.\n";
} else {
    echo "Error truncating table: " . $conn->error . "\n";
    $conn->close();
    exit(1);
}

// 2. Insert data from delivery_request_details
echo "\nPrefilling product_categories...\n";
$sql = "
    INSERT INTO product_categories (category_code, category_name, detail_category_code, detail_category_name, is_active, created_at)
    SELECT 
        prdct_clsfc_no,
        COALESCE(prdct_clsfc_no_nm, CONCAT('카테고리 ', prdct_clsfc_no)),
        dtil_prdct_clsfc_no,
        dtil_prdct_clsfc_no_nm,
        1,
        NOW()
    FROM (
        SELECT DISTINCT 
            prdct_clsfc_no, 
            prdct_clsfc_no_nm, 
            dtil_prdct_clsfc_no, 
            dtil_prdct_clsfc_no_nm
        FROM delivery_request_details
        WHERE prdct_clsfc_no IS NOT NULL AND prdct_clsfc_no != ''
    ) as distinct_categories
    ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);
";

if ($conn->query($sql) === TRUE) {
    $inserted_count = $conn->affected_rows;
    echo "Successfully inserted/updated {$inserted_count} categories.\n";
} else {
    echo "Error inserting data: " . $conn->error . "\n";
    $conn->close();
    exit(1);
}

// 3. Verify the count
echo "\nVerifying the final count...\n";
$result = $conn->query("SELECT COUNT(*) as total FROM product_categories");
$row = $result->fetch_assoc();
echo "Final count in product_categories: " . $row['total'] . "\n";


$conn->close();

echo "\nScript finished successfully.\n";

?> 