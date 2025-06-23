package com.example.smartgrocery.adapters

import android.content.Context
import android.content.Intent
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.PurchaseDetailActivity
import com.example.smartgrocery.PurchaseItem
import com.example.smartgrocery.R
import java.text.SimpleDateFormat
import java.util.Locale

class RecentPurchaseAdapter(
    private val context: Context,
    private val purchases: List<PurchaseItem>
) : RecyclerView.Adapter<RecentPurchaseAdapter.PurchaseViewHolder>() {

    inner class PurchaseViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val dateText: TextView = itemView.findViewById(R.id.purchaseDate)
        val itemsText: TextView = itemView.findViewById(R.id.purchaseItems)
        val amountText: TextView = itemView.findViewById(R.id.purchaseAmount)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): PurchaseViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_purchase_history_compact, parent, false)
        return PurchaseViewHolder(view)
    }

    override fun onBindViewHolder(holder: PurchaseViewHolder, position: Int) {
        val purchase = purchases[position]

        // Format date
        val formattedDate = SimpleDateFormat("MMM dd, yyyy - HH:mm", Locale.getDefault())
            .format(purchase.date)

        // Set text
        holder.dateText.text = formattedDate
        holder.itemsText.text = purchase.productsList
        holder.amountText.text = String.format("%.2f MAD", purchase.amount)

        // Set click listener
        holder.itemView.setOnClickListener {
            val intent = Intent(context, PurchaseDetailActivity::class.java).apply {
                putExtra("PURCHASE_ID", purchase.id)
            }
            context.startActivity(intent)
        }
    }

    override fun getItemCount() = purchases.size
}