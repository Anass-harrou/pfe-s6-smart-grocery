package com.example.smartgrocery

import java.util.Date

/**
 * Represents a purchase item with details
 */
data class PurchaseItem(
    val id: Int,
    val date: Date,
    val amount: Double,
    val productsList: String,
    val productId: Int = 0,
    val productName: String = "",
    val price: Double = 0.0,
    val quantity: Int = 0
)