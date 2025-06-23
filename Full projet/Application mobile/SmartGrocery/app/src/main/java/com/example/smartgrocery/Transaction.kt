package com.example.smartgrocery.model

data class Transaction(
    val id: Int,
    val title: String,
    val subtitle: String,
    val amount: Double,
    val type: String,
    val date: String,
    val source: String = "unknown" // Source of the transaction (transactions, achats, etc.)
)