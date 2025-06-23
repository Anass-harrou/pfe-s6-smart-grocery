package com.example.smartgrocery.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.PurchaseItem  // Make sure this import is present
import com.example.smartgrocery.R

class PurchaseItemAdapter(
    private val items: List<PurchaseItem>
) : RecyclerView.Adapter<PurchaseItemAdapter.ItemViewHolder>() {

    inner class ItemViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val productNameText: TextView = itemView.findViewById(R.id.product_name)
        val priceText: TextView = itemView.findViewById(R.id.price)
        val quantityText: TextView = itemView.findViewById(R.id.quantity)
        val totalText: TextView = itemView.findViewById(R.id.total)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ItemViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_purchase_detail, parent, false)
        return ItemViewHolder(view)
    }

    override fun onBindViewHolder(holder: ItemViewHolder, position: Int) {
        val item = items[position]

        holder.productNameText.text = item.productName
        holder.priceText.text = "${String.format("%.2f", item.price)} MAD"
        holder.quantityText.text = "×${item.quantity}"

        val totalPrice = item.price * item.quantity
        holder.totalText.text = "${String.format("%.2f", totalPrice)} MAD"
    }

    override fun getItemCount() = items.size
}