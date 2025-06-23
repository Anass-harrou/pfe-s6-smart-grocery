package com.example.smartgrocery.util

import android.content.Context
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import android.util.Log
import com.example.smartgrocery.model.Transaction
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

object ReceiptPdfGenerator {
    private const val TAG = "ReceiptPdfGenerator"
    private const val PAGE_WIDTH = 595 // A4 width in points
    private const val PAGE_HEIGHT = 842 // A4 height in points
    private const val MARGIN = 50
    private const val LINE_HEIGHT = 20

    // Current timestamp and user data (for audit purposes)
    private const val CURRENT_TIMESTAMP = "2025-06-19 15:45:56"
    private const val CURRENT_USER = "Anass-harrou"

    suspend fun generatePdf(context: Context, transaction: Transaction): File {
        return withContext(Dispatchers.IO) {
            // Determine if this is a purchase transaction or regular transaction
            val isPurchaseTransaction = transaction.source == "achats" || transaction.title.contains("Purchase", ignoreCase = true)

            // Create PDF document
            val document = PdfDocument()
            val pageInfo = PdfDocument.PageInfo.Builder(PAGE_WIDTH, PAGE_HEIGHT, 1).create()
            val page = document.startPage(pageInfo)
            val canvas = page.canvas

            // Get paint objects for text rendering
            val titlePaint = Paint().apply {
                textSize = 24f
                typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
                color = Color.BLACK
            }

            val subtitlePaint = Paint().apply {
                textSize = 16f
                typeface = Typeface.DEFAULT
                color = Color.DKGRAY
            }

            val regularPaint = Paint().apply {
                textSize = 14f
                color = Color.BLACK
            }

            val boldPaint = Paint().apply {
                textSize = 14f
                typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
                color = Color.BLACK
            }

            val smallPaint = Paint().apply {
                textSize = 12f
                color = Color.GRAY
            }

            // Start drawing the receipt
            var y = MARGIN + 20

            // Draw company logo or name
            canvas.drawText("SMART GROCERY", MARGIN.toFloat(), y.toFloat(), titlePaint)
            y += LINE_HEIGHT * 2

            // Current date and time
            val currentDate = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())
            canvas.drawText("Receipt Date: $currentDate", MARGIN.toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT * 2

            // Draw transaction details header
            if (isPurchaseTransaction) {
                canvas.drawText("PURCHASE RECEIPT", MARGIN.toFloat(), y.toFloat(), titlePaint)
            } else {
                canvas.drawText("TRANSACTION RECEIPT", MARGIN.toFloat(), y.toFloat(), titlePaint)
            }
            y += LINE_HEIGHT + 5

            // Transaction reference
            canvas.drawText(transaction.subtitle, MARGIN.toFloat(), y.toFloat(), subtitlePaint)
            y += LINE_HEIGHT * 2

            // Draw transaction information
            canvas.drawText("Transaction Date: ${formatDate(transaction.date)}", MARGIN.toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT

            canvas.drawText("Transaction ID: ${transaction.id}", MARGIN.toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT

            canvas.drawText("Type: ${formatTransactionType(transaction.type)}", MARGIN.toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT * 2

            // Draw separator line
            canvas.drawLine(MARGIN.toFloat(), y.toFloat(), (PAGE_WIDTH - MARGIN).toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT

            // If it's a purchase transaction, try to fetch and display purchase details
            if (isPurchaseTransaction) {
                try {
                    val purchaseDetails = fetchPurchaseDetails(context, transaction.id)
                    if (purchaseDetails.isNotEmpty()) {
                        // Draw purchase items
                        canvas.drawText("Items purchased:", MARGIN.toFloat(), y.toFloat(), boldPaint)
                        y += LINE_HEIGHT + 5

                        // Table header
                        val col1 = MARGIN.toFloat()
                        val col2 = MARGIN + 180f
                        val col3 = MARGIN + 240f
                        val col4 = MARGIN + 320f

                        canvas.drawText("Product", col1, y.toFloat(), boldPaint)
                        canvas.drawText("Qty", col2, y.toFloat(), boldPaint)
                        canvas.drawText("Price", col3, y.toFloat(), boldPaint)
                        canvas.drawText("Subtotal", col4, y.toFloat(), boldPaint)
                        y += LINE_HEIGHT

                        // Draw separator line
                        canvas.drawLine(MARGIN.toFloat(), y.toFloat(), (PAGE_WIDTH - MARGIN).toFloat(), y.toFloat(), regularPaint)
                        y += LINE_HEIGHT

                        // Draw items
                        for (item in purchaseDetails) {
                            val productName = item.getString("nom")
                            val quantity = item.getInt("quantite")
                            val price = item.getDouble("prix_unitaire")
                            val subtotal = quantity * price

                            // Truncate long product names
                            val displayName = if (productName.length > 24)
                                productName.substring(0, 21) + "..."
                            else productName

                            canvas.drawText(displayName, col1, y.toFloat(), regularPaint)
                            canvas.drawText(quantity.toString(), col2, y.toFloat(), regularPaint)
                            canvas.drawText("${formatPrice(price)} DH", col3, y.toFloat(), regularPaint)
                            canvas.drawText("${formatPrice(subtotal)} DH", col4, y.toFloat(), regularPaint)
                            y += LINE_HEIGHT

                            // Check if we need a new page
                            if (y > PAGE_HEIGHT - MARGIN * 2) {
                                // Finish current page and start a new one
                                document.finishPage(page)
                                val newPageInfo = PdfDocument.PageInfo.Builder(PAGE_WIDTH, PAGE_HEIGHT, document.pages.size + 1).create()
                                val newPage = document.startPage(newPageInfo)
                                // FIXED: Use a new local variable instead of reassigning canvas
                                val newCanvas = newPage.canvas
                                y = MARGIN

                                // Continue drawing on the new canvas
                                newCanvas.drawText("Items purchased (continued):", MARGIN.toFloat(), y.toFloat(), boldPaint)
                                y += LINE_HEIGHT
                            }
                        }
                    } else {
                        canvas.drawText("No purchase details available", MARGIN.toFloat(), y.toFloat(), regularPaint)
                        y += LINE_HEIGHT
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error fetching purchase details", e)
                    canvas.drawText("Error retrieving purchase details", MARGIN.toFloat(), y.toFloat(), regularPaint)
                    y += LINE_HEIGHT
                }
            }

            // Draw separator line
            canvas.drawLine(MARGIN.toFloat(), y.toFloat(), (PAGE_WIDTH - MARGIN).toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT * 2

            // Draw total amount
            canvas.drawText("Total Amount:", MARGIN.toFloat(), y.toFloat(), boldPaint)
            val amountText = "${formatPrice(transaction.amount)} DH"
            val amountWidth = boldPaint.measureText(amountText)
            canvas.drawText(amountText, (PAGE_WIDTH - MARGIN - amountWidth).toFloat(), y.toFloat(), boldPaint)
            y += LINE_HEIGHT * 2

            // Payment method (placeholder)
            canvas.drawText("Payment Method: Account Balance", MARGIN.toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT * 3

            // Thank you message
            canvas.drawText("Thank you for shopping with us!", MARGIN.toFloat(), y.toFloat(), subtitlePaint)
            y += LINE_HEIGHT
            canvas.drawText("Smart Grocery Store", MARGIN.toFloat(), y.toFloat(), regularPaint)
            y += LINE_HEIGHT

            // Footer
            y = PAGE_HEIGHT - MARGIN - LINE_HEIGHT * 3
            canvas.drawLine(MARGIN.toFloat(), y.toFloat(), (PAGE_WIDTH - MARGIN).toFloat(), y.toFloat(), smallPaint)
            y += LINE_HEIGHT

            val footerText = "Generated on: $CURRENT_TIMESTAMP | User: $CURRENT_USER"
            canvas.drawText(footerText, MARGIN.toFloat(), y.toFloat(), smallPaint)
            y += LINE_HEIGHT

            canvas.drawText("This receipt is computer generated and does not require signature.", MARGIN.toFloat(), y.toFloat(), smallPaint)

            // Finish the page and document
            document.finishPage(page)

            // Save the document to a file
            val fileName = "Receipt_${transaction.id}_${System.currentTimeMillis()}.pdf"
            val filePath = File(context.getExternalFilesDir(null), fileName)

            try {
                FileOutputStream(filePath).use { out ->
                    document.writeTo(out)
                }
            } catch (e: IOException) {
                Log.e(TAG, "Error writing PDF file", e)
                throw e
            } finally {
                document.close()
            }

            Log.d(TAG, "PDF saved to: ${filePath.absolutePath}")
            return@withContext filePath
        }
    }

    private suspend fun fetchPurchaseDetails(context: Context, purchaseId: Int): List<JSONObject> {
        return withContext(Dispatchers.IO) {
            try {
                // In a real app, this would make an API call to fetch purchase details
                // For this example, we'll create mock data based on the purchase ID
                val items = mutableListOf<JSONObject>()

                // Here we would normally make an API call to get purchase items
                // For demo purposes, create mock items
                val mockProducts = listOf(
                    Pair("Milk", 12.50),
                    Pair("Bread", 5.00),
                    Pair("Eggs (12)", 18.75),
                    Pair("Cheese", 35.00),
                    Pair("Tomatoes", 8.50),
                    Pair("Rice (1kg)", 14.25),
                    Pair("Chicken", 45.00),
                    Pair("Pasta", 7.00)
                )

                // Generate 2-5 items based on purchase ID
                val itemCount = (purchaseId % 4) + 2
                for (i in 0 until itemCount) {
                    val productIndex = (purchaseId + i) % mockProducts.size
                    val quantity = (purchaseId % 3) + 1

                    val item = JSONObject().apply {
                        put("nom", mockProducts[productIndex].first)
                        put("quantite", quantity)
                        put("prix_unitaire", mockProducts[productIndex].second)
                    }
                    items.add(item)
                }

                return@withContext items
            } catch (e: Exception) {
                Log.e(TAG, "Error fetching purchase details", e)
                return@withContext emptyList()
            }
        }
    }

    private fun formatDate(dateString: String): String {
        try {
            val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
            val outputFormat = SimpleDateFormat("MMM dd, yyyy HH:mm", Locale.getDefault())
            val date = inputFormat.parse(dateString)
            return outputFormat.format(date ?: Date())
        } catch (e: Exception) {
            // If parsing fails, return the original string
            return dateString
        }
    }

    private fun formatTransactionType(type: String): String {
        return when (type.lowercase()) {
            "credit" -> "Credit (Deposit)"
            "debit" -> "Debit (Purchase)"
            else -> type.capitalize(Locale.getDefault())
        }
    }

    private fun formatPrice(price: Double): String {
        return String.format(Locale.getDefault(), "%.2f", price)
    }
}