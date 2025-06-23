package com.example.smartgrocery.models

/**
 * Data class to represent a purchase in the user's history
 */
data class PurchaseHistory(
    val id: String,
    val date: String,
    val total: Double,
    val products: List<Product> = emptyList(),
    val paymentMethod: String = "Card",
    val status: String = "Completed"
) {
    /**
     * Data class to represent a product in a purchase
     */
    data class Product(
        val id: String,
        val name: String,
        val price: Double,
        val quantity: Int
    )
}