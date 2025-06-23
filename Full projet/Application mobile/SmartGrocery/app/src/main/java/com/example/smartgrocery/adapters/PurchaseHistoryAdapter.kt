package com.example.smartgrocery.adapters

import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Environment
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageButton
import android.widget.TextView
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.PurchaseDetailActivity
import com.example.smartgrocery.PurchaseItem
import com.example.smartgrocery.R
import java.text.SimpleDateFormat
import java.util.Locale

class PurchaseHistoryAdapter(
    private val context: Context,
    private val purchases: List<PurchaseItem>
) : RecyclerView.Adapter<PurchaseHistoryAdapter.PurchaseViewHolder>() {

    inner class PurchaseViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val dateText: TextView = itemView.findViewById(R.id.purchase_date)
        val amountText: TextView = itemView.findViewById(R.id.purchase_amount)
        val productsText: TextView = itemView.findViewById(R.id.purchase_products)
        val detailsButton: TextView = itemView.findViewById(R.id.view_details)
        val pdfButton: ImageButton = itemView.findViewById(R.id.download_pdf)
        val csvButton: ImageButton = itemView.findViewById(R.id.download_csv)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): PurchaseViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_purchase_history, parent, false)
        return PurchaseViewHolder(view)
    }

    override fun onBindViewHolder(holder: PurchaseViewHolder, position: Int) {
        val purchase = purchases[position]

        // Format date
        val formattedDate = SimpleDateFormat("MMM dd, yyyy HH:mm", Locale.getDefault())
            .format(purchase.date)

        // Set calendar icon
        holder.dateText.compoundDrawablePadding = 8
        val calendarIcon = ContextCompat.getDrawable(context, R.drawable.ic_calendar)
        calendarIcon?.setBounds(0, 0, calendarIcon.intrinsicWidth, calendarIcon.intrinsicHeight)
        holder.dateText.setCompoundDrawables(calendarIcon, null, null, null)

        // Set text
        holder.dateText.text = formattedDate
        holder.amountText.text = "${purchase.amount} MAD"
        holder.productsText.text = purchase.productsList

        // Set up "View Details" button
        holder.detailsButton.setOnClickListener {
            val intent = Intent(context, PurchaseDetailActivity::class.java).apply {
                putExtra("PURCHASE_ID", purchase.id)
            }
            context.startActivity(intent)
        }

        // Also make the entire item clickable to view details
        holder.itemView.setOnClickListener {
            val intent = Intent(context, PurchaseDetailActivity::class.java).apply {
                putExtra("PURCHASE_ID", purchase.id)
            }
            context.startActivity(intent)
        }

        // Set up PDF download
        holder.pdfButton.setOnClickListener {
            downloadReceipt(purchase.id, "pdf")
        }

        // Set up CSV download
        holder.csvButton.setOnClickListener {
            downloadReceipt(purchase.id, "csv")
        }
    }

    private fun downloadReceipt(purchaseId: Int, format: String) {
        // Use the server URL from the app's configuration
        val serverUrl = context.getString(R.string.api_base_url)
        val url = "$serverUrl/generate_receipt.php?id=$purchaseId&format=$format"

        try {
            // Create download request
            val request = DownloadManager.Request(Uri.parse(url))
                .setTitle("Receipt #$purchaseId")
                .setDescription("Downloading purchase receipt")
                .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                .setDestinationInExternalPublicDir(
                    Environment.DIRECTORY_DOWNLOADS,
                    "SmartGrocery_Receipt_$purchaseId.$format"
                )

            // Get download service and enqueue the request
            val downloadManager = context.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
            downloadManager.enqueue(request)

            Toast.makeText(
                context,
                "Downloading receipt in $format format...",
                Toast.LENGTH_SHORT
            ).show()
        } catch (e: Exception) {
            Toast.makeText(
                context,
                "Error: ${e.message}",
                Toast.LENGTH_LONG
            ).show()
        }
    }

    override fun getItemCount() = purchases.size
}