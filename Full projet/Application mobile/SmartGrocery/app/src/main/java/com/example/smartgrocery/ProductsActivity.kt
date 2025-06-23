package com.example.smartgrocery

import android.os.Bundle
import android.util.Log
import android.view.MenuItem
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.adapters.ProductAdapter
import com.example.smartgrocery.models.Product

/**
 * Products Browsing Activity for Smart Grocery App
 * Current Date and Time: 2025-06-23 03:41:16
 * Author: Anass-harrou
 */
class ProductsActivity : AppCompatActivity() {

    private val TAG = "ProductsActivity"
    private val products = ArrayList<Product>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_products)
        Log.d(TAG, "onCreate: Starting ProductsActivity")

        // Set up toolbar
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Browse Products"

        // Set current date and user as subtitle
        supportActionBar?.subtitle = "2025-06-23 03:41:16 | Anass-harrou"

        // Initialize RecyclerView
        try {
            val productsRecyclerView = findViewById<RecyclerView>(R.id.productsRecyclerView)
            productsRecyclerView.layoutManager = GridLayoutManager(this, 2)

            // Load sample products
            loadSampleProducts()

            // Set adapter
            val adapter = ProductAdapter(this, products) { product ->
                if (product.quantity > 0) {
                    Toast.makeText(this, "${product.name} est disponible (${product.quantity} en stock)", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(this, "${product.name} est actuellement indisponible", Toast.LENGTH_SHORT).show()
                }
            }
            productsRecyclerView.adapter = adapter
            Log.d(TAG, "RecyclerView setup complete with ${products.size} products")

        } catch (e: Exception) {
            Log.e(TAG, "Error setting up RecyclerView: ${e.message}")
            Toast.makeText(this, "Error: ${e.message}", Toast.LENGTH_LONG).show()
        }
    }

    private fun loadSampleProducts() {
        // Sample data that matches your database structure
        products.add(
            Product(
                id = 1,
                name = "Lait frais",
                price = 25.00,
                imageUrl = "",
                description = "Lait frais pasteurisé",
                category = "produits laitiers",
                quantity = 10
            )
        )

        products.add(
            Product(
                id = 2,
                name = "Pommes rouges (1kg)",
                price = 12.50,
                imageUrl = "",
                description = "Pommes juteuses et sucrées",
                category = "fruits",
                quantity = 25
            )
        )

        products.add(
            Product(
                id = 3,
                name = "Pain complet",
                price = 8.75,
                imageUrl = "",
                description = "Pain aux céréales complètes",
                category = "boulangerie",
                quantity = 5
            )
        )

        products.add(
            Product(
                id = 4,
                name = "Filet de poulet (500g)",
                price = 45.00,
                imageUrl = "",
                description = "Filet de poulet premium",
                category = "viande",
                quantity = 0
            )
        )

        products.add(
            Product(
                id = 5,
                name = "Huile d'olive (750ml)",
                price = 65.00,
                imageUrl = "",
                description = "Huile d'olive extra vierge",
                category = "épicerie",
                quantity = 15
            )
        )

        products.add(
            Product(
                id = 6,
                name = "Tomates (1kg)",
                price = 7.50,
                imageUrl = "",
                description = "Tomates fraîches locales",
                category = "légumes",
                quantity = 0
            )
        )
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (item.itemId == android.R.id.home) {
            finish()
            return true
        }
        return super.onOptionsItemSelected(item)
    }
}