package com.example.smartgrocery

import android.app.AlertDialog
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.MenuItem
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.FileProvider
import com.example.smartgrocery.databinding.ActivityReceiptBinding
import com.example.smartgrocery.model.Transaction
import com.example.smartgrocery.util.ReceiptCsvGenerator
import com.example.smartgrocery.util.ReceiptPdfGenerator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class ReceiptActivity : AppCompatActivity() {

    private lateinit var binding: ActivityReceiptBinding
    private val TAG = "ReceiptActivity"

    // Audit information (as specified)
    private val currentTimestamp = "2025-06-18 16:19:14" // UTC YYYY-MM-DD HH:MM:SS
    private val currentUser = "Anass-harrou"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityReceiptBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Set up toolbar
        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Transaction Receipt"

        // Get transaction data from intent
        val transactionId = intent.getIntExtra("TRANSACTION_ID", -1)
        if (transactionId == -1) {
            Toast.makeText(this, "Invalid transaction", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        // Create transaction object from intent extras
        val transaction = Transaction(
            id = transactionId,
            title = intent.getStringExtra("TRANSACTION_TITLE") ?: "",
            subtitle = intent.getStringExtra("TRANSACTION_SUBTITLE") ?: "",
            amount = intent.getDoubleExtra("TRANSACTION_AMOUNT", 0.0),
            type = intent.getStringExtra("TRANSACTION_TYPE") ?: "debit",
            date = intent.getStringExtra("TRANSACTION_DATE") ?: "",
            source = intent.getStringExtra("TRANSACTION_SOURCE") ?: "unknown"
        )

        // Display transaction details
        displayTransactionDetails(transaction)

        // Set up download buttons
        binding.pdfButton.setOnClickListener {
            downloadAsPdf(transaction)
        }

        binding.csvButton.setOnClickListener {
            downloadAsCsv(transaction)
        }

        binding.shareButton.setOnClickListener {
            shareReceiptDetails(transaction)
        }

        // Display audit timestamp
        binding.auditTimestampView.text = "Generated: $currentTimestamp | User: $currentUser"
    }

    private fun displayTransactionDetails(transaction: Transaction) {
        binding.titleTextView.text = transaction.title
        binding.subtitleTextView.text = transaction.subtitle

        // Format amount with appropriate sign and currency
        val formattedAmount = formatAmount(transaction.amount, transaction.type)
        binding.amountTextView.text = formattedAmount

        // Format date for better readability
        binding.dateTextView.text = formatDate(transaction.date)

        // Set transaction type with icon
        val typeText = when (transaction.type.toLowerCase(Locale.ROOT)) {
            "credit" -> "Credit (Deposit)"
            "debit" -> "Debit (Purchase)"
            else -> transaction.type.capitalize(Locale.ROOT)
        }
        binding.typeTextView.text = typeText

        // Set color based on transaction type
        val colorResId = if (isCredit(transaction.type))
            R.color.transaction_credit
        else
            R.color.transaction_debit
        binding.amountTextView.setTextColor(getColor(colorResId))

        // Set transaction icon based on source and type
        val iconResId = when {
            transaction.source == "achats" ||
                    transaction.title.contains("Purchase", ignoreCase = true) ->
                R.drawable.ic_shopping_cart // Replace with your actual drawable
            isCredit(transaction.type) ->
                R.drawable.ic_money_in // Replace with your actual drawable
            else ->
                R.drawable.ic_money_out // Replace with your actual drawable
        }
        binding.transactionIconView.setImageResource(iconResId)

        // Set transaction source info if available
        if (transaction.source.isNotEmpty() && transaction.source != "unknown") {
            binding.sourceTextView.visibility = View.VISIBLE
            binding.sourceTextView.text = "Source: ${transaction.source.capitalize(Locale.ROOT)}"
        } else {
            binding.sourceTextView.visibility = View.GONE
        }

        // Set transaction ID
        binding.transactionIdView.text = "Transaction #${transaction.id}"

        // Show or hide purchase details section based on transaction type
        if (transaction.source == "achats" || transaction.title.contains("Purchase", ignoreCase = true)) {
            binding.purchaseDetailsSection.visibility = View.VISIBLE
            loadPurchaseDetails(transaction.id)
        } else {
            binding.purchaseDetailsSection.visibility = View.GONE
        }
    }

    private fun loadPurchaseDetails(purchaseId: Int) {
        // Show loading indicator
        binding.purchaseDetailsProgressBar.visibility = View.VISIBLE
        binding.purchaseDetailsList.visibility = View.GONE

        // In a real app, you would fetch purchase details from API
        // For this example, we'll simulate loading with a delay
        CoroutineScope(Dispatchers.IO).launch {
            try {
                // Simulate network delay
                kotlinx.coroutines.delay(1000)

                // Switch to main thread to update UI
                withContext(Dispatchers.Main) {
                    // Hide loading indicator
                    binding.purchaseDetailsProgressBar.visibility = View.GONE
                    binding.purchaseDetailsList.visibility = View.VISIBLE

                    // Create mock purchase details
                    val detailsText = generateMockPurchaseDetails(purchaseId)
                    binding.purchaseDetailsList.text = detailsText
                }
            } catch (e: Exception) {
                withContext(Dispatchers.Main) {
                    binding.purchaseDetailsProgressBar.visibility = View.GONE
                    binding.purchaseDetailsList.text = "Error loading purchase details: ${e.message}"
                }
            }
        }
    }

    private fun generateMockPurchaseDetails(purchaseId: Int): String {
        val mockProducts = listOf(
            Triple("Milk", 12.50, 2),
            Triple("Bread", 5.00, 1),
            Triple("Eggs (12)", 18.75, 1),
            Triple("Cheese", 35.00, 1),
            Triple("Tomatoes", 8.50, 3),
            Triple("Rice (1kg)", 14.25, 1),
            Triple("Chicken", 45.00, 1),
            Triple("Pasta", 7.00, 2)
        )

        val itemCount = (purchaseId % 4) + 2
        var totalAmount = 0.0
        val details = StringBuilder()

        details.append("ITEMS PURCHASED:\n\n")
        details.append(String.format("%-20s %-10s %-12s %-12s\n", "Product", "Qty", "Price", "Subtotal"))
        details.append("------------------------------------------------------\n")

        for (i in 0 until itemCount) {
            val productIndex = (purchaseId + i) % mockProducts.size
            val (product, price, qty) = mockProducts[productIndex]
            val subtotal = price * qty
            totalAmount += subtotal

            details.append(
                String.format(
                    "%-20s %-10d %-12s %-12s\n",
                    product,
                    qty,
                    "${formatPrice(price)} DH",
                    "${formatPrice(subtotal)} DH"
                )
            )
        }

        details.append("------------------------------------------------------\n")
        details.append(String.format("%-44s %-12s\n", "TOTAL:", "${formatPrice(totalAmount)} DH"))

        return details.toString()
    }

    private fun downloadAsPdf(transaction: Transaction) {
        binding.downloadProgressBar.visibility = View.VISIBLE

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val file = ReceiptPdfGenerator.generatePdf(this@ReceiptActivity, transaction)

                withContext(Dispatchers.Main) {
                    binding.downloadProgressBar.visibility = View.GONE
                    showFileDownloadedDialog("PDF", file)
                }
            } catch (e: Exception) {
                withContext(Dispatchers.Main) {
                    binding.downloadProgressBar.visibility = View.GONE
                    Toast.makeText(
                        this@ReceiptActivity,
                        "Error: ${e.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    private fun downloadAsCsv(transaction: Transaction) {
        binding.downloadProgressBar.visibility = View.VISIBLE

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val file = ReceiptCsvGenerator.generateCsv(this@ReceiptActivity, transaction)

                withContext(Dispatchers.Main) {
                    binding.downloadProgressBar.visibility = View.GONE
                    showFileDownloadedDialog("CSV", file)
                }
            } catch (e: Exception) {
                withContext(Dispatchers.Main) {
                    binding.downloadProgressBar.visibility = View.GONE
                    Toast.makeText(
                        this@ReceiptActivity,
                        "Error: ${e.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    private fun shareReceiptDetails(transaction: Transaction) {
        val shareText = StringBuilder()

        // Format the receipt information
        shareText.append("SMART GROCERY - TRANSACTION RECEIPT\n\n")
        shareText.append("Transaction #${transaction.id}\n")
        shareText.append("Type: ${if (isCredit(transaction.type)) "Credit" else "Debit"}\n")
        shareText.append("Date: ${formatDate(transaction.date)}\n")
        shareText.append("Amount: ${formatAmount(transaction.amount, transaction.type)}\n")
        shareText.append("Description: ${transaction.subtitle}\n\n")

        // Add audit info
        shareText.append("Generated: $currentTimestamp\n")
        shareText.append("User: $currentUser\n")

        // Create intent
        val intent = Intent(Intent.ACTION_SEND)
        intent.type = "text/plain"
        intent.putExtra(Intent.EXTRA_SUBJECT, "Receipt for Transaction #${transaction.id}")
        intent.putExtra(Intent.EXTRA_TEXT, shareText.toString())

        startActivity(Intent.createChooser(intent, "Share Receipt Via"))
    }

    private fun showFileDownloadedDialog(fileType: String, file: File) {
        AlertDialog.Builder(this)
            .setTitle("$fileType Generated")
            .setMessage("Your $fileType file has been saved to:\n\n${file.absolutePath}")
            .setPositiveButton("View") { _, _ ->
                openFile(file, fileType.toLowerCase(Locale.ROOT))
            }
            .setNegativeButton("Close", null)
            .show()
    }

    private fun openFile(file: File, fileType: String) {
        try {
            val uri = FileProvider.getUriForFile(
                this,
                "${applicationContext.packageName}.fileprovider",
                file
            )

            val intent = Intent(Intent.ACTION_VIEW).apply {
                val mimeType = when (fileType) {
                    "pdf" -> "application/pdf"
                    "csv" -> "text/csv"
                    else -> "*/*"
                }
                setDataAndType(uri, mimeType)
                flags = Intent.FLAG_ACTIVITY_NO_HISTORY
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            }

            startActivity(Intent.createChooser(intent, "Open with"))
        } catch (e: Exception) {
            Toast.makeText(
                this,
                "No app found to open this file: ${e.message}",
                Toast.LENGTH_LONG
            ).show()
        }
    }

    // Helper functions
    private fun isCredit(type: String): Boolean {
        return type.equals("credit", ignoreCase = true)
    }

    private fun formatAmount(amount: Double, type: String): String {
        val prefix = if (isCredit(type)) "+" else "-"
        return "$prefix${formatPrice(amount)} DH"
    }

    private fun formatPrice(price: Double): String {
        return String.format(Locale.getDefault(), "%.2f", price)
    }

    private fun formatDate(dateString: String): String {
        return try {
            val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
            val outputFormat = SimpleDateFormat("MMMM d, yyyy 'at' HH:mm", Locale.getDefault())
            val date = inputFormat.parse(dateString)
            outputFormat.format(date ?: Date())
        } catch (e: Exception) {
            // If parsing fails, return the original string
            dateString
        }
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (item.itemId == android.R.id.home) {
            finish()
            return true
        }
        return super.onOptionsItemSelected(item)
    }
}