package com.example.smartgrocery.models

data class Product(
    val id: Int,
    val name: String,
    val price: Double,
    val imageUrl: String,
    val description: String,
    val category: String,
    val quantity: Int
)