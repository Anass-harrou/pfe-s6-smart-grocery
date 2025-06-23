package com.example.smartgrocery

import android.app.DownloadManager
import android.content.Context
import android.net.Uri
import android.os.Bundle
import android.os.Environment
import android.util.Log
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.android.volley.Request
import com.android.volley.toolbox.JsonObjectRequest
import com.android.volley.toolbox.Volley
import com.example.smartgrocery.adapters.PurchaseItemAdapter
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale


class PurchaseDetailActivity : AppCompatActivity() {

    private lateinit var receiptNumberText: TextView
    private lateinit var dateText: TextView
    private lateinit var customerNameText: TextView
    private lateinit var customerEmailText: TextView
    private lateinit var customerPhoneText: TextView
    private lateinit var totalAmountText: TextView
    private lateinit var itemsRecyclerView: RecyclerView
    private lateinit var progressBar: ProgressBar
    private lateinit var errorText: TextView
    private lateinit var pdfButton: Button
    private lateinit var csvButton: Button

    private var purchaseId: Int = 0

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_purchase_detail)

        // Initialize UI components
        receiptNumberText = findViewById(R.id.receipt_number)
        dateText = findViewById(R.id.purchase_date)
        customerNameText = findViewById(R.id.customer_name)
        customerEmailText = findViewById(R.id.customer_email)
        customerPhoneText = findViewById(R.id.customer_phone)
        totalAmountText = findViewById(R.id.total_amount)
        itemsRecyclerView = findViewById(R.id.items_recycler_view)
        progressBar = findViewById(R.id.progress_bar)
        errorText = findViewById(R.id.error_text)
        pdfButton = findViewById(R.id.pdf_button)
        csvButton = findViewById(R.id.csv_button)

        // Set up toolbar
        setSupportActionBar(findViewById(R.id.toolbar))
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Purchase Details"

        // Get purchase ID from intent
        purchaseId = intent.getIntExtra("PURCHASE_ID", 0)
        if (purchaseId <= 0) {
            showError("Invalid purchase ID")
            return
        }

        // Set up RecyclerView
        itemsRecyclerView.layoutManager = LinearLayoutManager(this)

        // Load purchase details
        loadPurchaseDetails(purchaseId)

        // Set up download buttons
        pdfButton.setOnClickListener { downloadReceipt("pdf") }
        csvButton.setOnClickListener { downloadReceipt("csv") }
    }

    private fun loadPurchaseDetails(purchaseId: Int) {
        showLoading(true)

        // API endpoint
        val url = "${getString(R.string.api_base_url)}/get_purchase_detail.php?id=$purchaseId"

        val request = JsonObjectRequest(
            Request.Method.GET, url, null,
            { response ->
                try {
                    // Parse purchase details
                    val purchase = response.getJSONObject("purchase")
                    val items = response.getJSONArray("items")

                    // Set purchase details
                    receiptNumberText.text = "#${purchase.getInt("id_achat")}"

                    val dateStr = purchase.getString("date_achat")
                    val dateFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                    val date = dateFormat.parse(dateStr) ?: Date()
                    val formattedDate = SimpleDateFormat("EEEE, MMM dd, yyyy HH:mm", Locale.getDefault())
                        .format(date)
                    dateText.text = formattedDate

                    customerNameText.text = purchase.getString("customer_name")

                    if (purchase.has("customer_email") && !purchase.isNull("customer_email")) {
                        customerEmailText.text = purchase.getString("customer_email")
                        customerEmailText.visibility = View.VISIBLE
                        findViewById<TextView>(R.id.customer_email_label).visibility = View.VISIBLE
                    } else {
                        customerEmailText.visibility = View.GONE
                        findViewById<TextView>(R.id.customer_email_label).visibility = View.GONE
                    }

                    if (purchase.has("customer_phone") && !purchase.isNull("customer_phone")) {
                        customerPhoneText.text = purchase.getString("customer_phone")
                        customerPhoneText.visibility = View.VISIBLE
                        findViewById<TextView>(R.id.customer_phone_label).visibility = View.VISIBLE
                    } else {
                        customerPhoneText.visibility = View.GONE
                        findViewById<TextView>(R.id.customer_phone_label).visibility = View.GONE
                    }

                    val amount = purchase.getDouble("montant_total")
                    totalAmountText.text = "${String.format("%.2f", amount)} MAD"

                    // Set up items
                    val itemsList = ArrayList<PurchaseItem>()
                    for (i in 0 until items.length()) {
                        val item = items.getJSONObject(i)
                        itemsList.add(
                            PurchaseItem(
                                id = item.getInt("id_achat_produit"),
                                productId = item.getInt("id_produit"),
                                productName = item.getString("product_name"),
                                price = item.getDouble("prix_unitaire"),
                                quantity = item.getInt("quantite"),
                                date = Date(), // Default value, won't be used in this context
                                amount = 0.0,  // Default value, won't be used in this context
                                productsList = "" // Default value, won't be used in this context
                            )
                        )
                    }

                    val adapter = PurchaseItemAdapter(itemsList)
                    itemsRecyclerView.adapter = adapter

                    showLoading(false)
                } catch (e: Exception) {
                    Log.e("PurchaseDetail", "Error parsing data", e)
                    showError("Error parsing purchase data: ${e.message}")
                }
            },
            { error ->
                Log.e("PurchaseDetail", "Error loading data", error)
                showError("Error loading purchase details: ${error.message}")
            }
        )

        // Add request to queue
        Volley.newRequestQueue(this).add(request)
    }

    private fun showLoading(isLoading: Boolean) {
        progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE

        val contentViews = arrayOf(
            receiptNumberText, dateText, customerNameText,
            customerEmailText, customerPhoneText, totalAmountText,
            itemsRecyclerView, pdfButton, csvButton
        )

        for (view in contentViews) {
            view.visibility = if (isLoading) View.GONE else View.VISIBLE
        }

        errorText.visibility = View.GONE
    }

    private fun showError(message: String) {
        progressBar.visibility = View.GONE

        val contentViews = arrayOf(
            receiptNumberText, dateText, customerNameText,
            customerEmailText, customerPhoneText, totalAmountText,
            itemsRecyclerView, pdfButton, csvButton
        )

        for (view in contentViews) {
            view.visibility = View.GONE
        }

        errorText.visibility = View.VISIBLE
        errorText.text = message
    }

    private fun downloadReceipt(format: String) {
        val serverUrl = getString(R.string.api_base_url)

        // Only send purchase ID, not user info
        val url = "$serverUrl/generate_receipt.php?id=$purchaseId&format=$format"

        try {
            val request = DownloadManager.Request(Uri.parse(url))
                .setTitle("Receipt #$purchaseId")
                .setDescription("Downloading purchase receipt")
                .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                .setDestinationInExternalPublicDir(
                    Environment.DIRECTORY_DOWNLOADS,
                    "SmartGrocery_Receipt_$purchaseId.$format"
                )

            val downloadManager = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
            downloadManager.enqueue(request)

            Toast.makeText(
                this,
                "Downloading receipt in $format format...",
                Toast.LENGTH_SHORT
            ).show()
        } catch (e: Exception) {
            Toast.makeText(
                this,
                "Error: ${e.message}",
                Toast.LENGTH_LONG
            ).show()
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }
}