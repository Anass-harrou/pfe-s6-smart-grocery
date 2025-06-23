package com.example.smartgrocery.adapters

import android.content.Context
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.R
import com.example.smartgrocery.models.Product

/**
 * Product Adapter for Smart Grocery App
 * Current Date and Time: 2025-06-23 03:41:16
 * Author: Anass-harrou
 */
class ProductAdapter(
    private val context: Context,
    private val products: List<Product>,
    private val onProductClick: (Product) -> Unit
) : RecyclerView.Adapter<ProductAdapter.ProductViewHolder>() {

    class ProductViewHolder(view: View) : RecyclerView.ViewHolder(view) {
        val productName: TextView = view.findViewById(R.id.productName)
        val productPrice: TextView = view.findViewById(R.id.productPrice)
        val stockStatus: TextView = view.findViewById(R.id.stockStatus)
        val addToCartButton: Button = view.findViewById(R.id.addToCartButton)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ProductViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_product, parent, false)
        return ProductViewHolder(view)
    }

    override fun onBindViewHolder(holder: ProductViewHolder, position: Int) {
        val product = products[position]

        // Set product name and price
        holder.productName.text = product.name
        holder.productPrice.text = String.format("%.2f MAD", product.price)

        // Set stock status
        if (product.quantity > 0) {
            holder.stockStatus.text = "Disponible (${product.quantity})"
            holder.stockStatus.setTextColor(context.getColor(R.color.green_success))
            holder.addToCartButton.text = "Disponible"
            holder.addToCartButton.setBackgroundColor(context.getColor(R.color.green_success))
            holder.addToCartButton.isEnabled = true
        } else {
            holder.stockStatus.text = "Indisponible"
            holder.stockStatus.setTextColor(context.getColor(R.color.red_error))
            holder.addToCartButton.text = "Indisponible"
            holder.addToCartButton.setBackgroundColor(context.getColor(R.color.red_error))
            holder.addToCartButton.isEnabled = false
        }

        // Set click listeners
        holder.itemView.setOnClickListener { onProductClick(product) }
        holder.addToCartButton.setOnClickListener {
            if (product.quantity > 0) {
                onProductClick(product)
            }
        }
    }

    override fun getItemCount() = products.size
}