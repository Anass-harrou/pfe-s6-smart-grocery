<?php
/**
 * Products API for Smart Grocery App
 * Date: 2025-06-23 03:10:41
 * Author: Anass-harrou
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "gestion_stock";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Base URL for product images
$base_image_url = "http://192.168.1.10/gestion_stock/images/products/";

// Process request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'get_all':
            getAllProducts();
            break;
            
        case 'get_by_category':
            $category = isset($_POST['category']) ? $_POST['category'] : '';
            getProductsByCategory($category);
            break;
            
        case 'search':
            $query = isset($_POST['query']) ? $_POST['query'] : '';
            searchProducts($query);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
}

function getAllProducts() {
    global $conn, $base_image_url;
    
    $sql = "SELECT * FROM produits ORDER BY nom ASC";
    $result = $conn->query($sql);
    
    if ($result) {
        $products = array();
        
        while ($row = $result->fetch_assoc()) {
            // Process image URL
            $image_url = $row['image'] ? $base_image_url . $row['image'] : "";
            
            // Add product to array
            $products[] = [
                'id' => $row['id'],
                'nom' => $row['nom'],
                'prix' => $row['prix'],
                'description' => $row['description'] ?? '',
                'categorie' => $row['categorie'],
                'quantite' => $row['quantite'],
                'image_url' => $image_url
            ];
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving products: ' . $conn->error
        ]);
    }
}

function getProductsByCategory($category) {
    global $conn, $base_image_url;
    
    // Sanitize input
    $category = $conn->real_escape_string($category);
    
    $sql = "SELECT * FROM produits WHERE categorie LIKE '%$category%' ORDER BY nom ASC";
    $result = $conn->query($sql);
    
    if ($result) {
        $products = array();
        
        while ($row = $result->fetch_assoc()) {
            // Process image URL
            $image_url = $row['image'] ? $base_image_url . $row['image'] : "";
            
            // Add product to array
            $products[] = [
                'id' => $row['id'],
                'nom' => $row['nom'],
                'prix' => $row['prix'],
                'description' => $row['description'] ?? '',
                'categorie' => $row['categorie'],
                'quantite' => $row['quantite'],
                'image_url' => $image_url
            ];
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving products: ' . $conn->error
        ]);
    }
}

function searchProducts($query) {
    global $conn, $base_image_url;
    
    // Sanitize input
    $query = $conn->real_escape_string($query);
    
    $sql = "SELECT * FROM produits WHERE 
            nom LIKE '%$query%' OR 
            description LIKE '%$query%' OR 
            categorie LIKE '%$query%'
            ORDER BY nom ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $products = array();
        
        while ($row = $result->fetch_assoc()) {
            // Process image URL
            $image_url = $row['image'] ? $base_image_url . $row['image'] : "";
            
            // Add product to array
            $products[] = [
                'id' => $row['id'],
                'nom' => $row['nom'],
                'prix' => $row['prix'],
                'description' => $row['description'] ?? '',
                'categorie' => $row['categorie'],
                'quantite' => $row['quantite'],
                'image_url' => $image_url
            ];
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error searching products: ' . $conn->error
        ]);
    }
}

$conn->close();
?>