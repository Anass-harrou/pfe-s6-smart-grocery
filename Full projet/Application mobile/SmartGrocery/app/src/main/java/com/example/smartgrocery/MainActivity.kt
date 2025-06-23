package com.example.smartgrocery

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.view.View
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.adapters.RecentPurchaseAdapter
import com.google.android.material.card.MaterialCardView
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Main Dashboard Activity for Smart Grocery App
 * Date: 2025-06-23 03:00:15
 * Author: Anass-harrou
 */
class MainActivity : AppCompatActivity() {

    private lateinit var balanceText: TextView
    private lateinit var cardNumberText: TextView
    private lateinit var cardNameTextView: TextView
    private lateinit var expiryDateText: TextView
    private lateinit var copyButton: TextView
    private lateinit var loyaltyCardLabel: TextView

    private lateinit var scanAction: View
    private lateinit var historyAction: View
    private lateinit var productsAction: View
    private lateinit var profileAction: View

    private lateinit var recentPurchasesRecyclerView: RecyclerView
    private lateinit var emptyHistoryText: TextView

    private val recentPurchases = ArrayList<PurchaseItem>()
    private val TAG = "MainActivity"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        // Initialize views
        balanceText = findViewById(R.id.balanceText)
        cardNumberText = findViewById(R.id.cardNumberText)
        cardNameTextView = findViewById(R.id.cardNameTextView)
        expiryDateText = findViewById(R.id.expiryDateText)
        copyButton = findViewById(R.id.copyButton)
        loyaltyCardLabel = findViewById(R.id.textView3)

        // Change "Loyalty Card" to "RFID Card"
        loyaltyCardLabel.text = "RFID Card"

        // Action cards
        scanAction = findViewById(R.id.scanAction)
        historyAction = findViewById(R.id.historyAction)
        productsAction = findViewById(R.id.productsAction)
        profileAction = findViewById(R.id.profileAction)

        // Recent purchases
        recentPurchasesRecyclerView = findViewById(R.id.recentPurchasesRecyclerView)
        emptyHistoryText = findViewById(R.id.emptyHistoryText)

        // Set up customer info
        setupCustomerInfo()

        // Set up action icons and labels
        setupActionCards()

        // Set up recent purchases
        setupRecentPurchases()

        // Copy button listener
        copyButton.setOnClickListener {
            copyRfidToClipboard()
        }
    }

    private fun setupCustomerInfo() {
        // Get customer info from preferences
        val prefs = getSharedPreferences("user_data", MODE_PRIVATE)

        // Get user data
        val name = prefs.getString("name", "Guest User") ?: "Guest User"
        val soldeStr = prefs.getString("solde", "0.00") ?: "0.00"

        // Get RFID - using the standard key name ('rfid') in SharedPreferences
        val rfid = prefs.getString("rfid", "") ?: ""

        Log.d(TAG, "Retrieved data - name: $name, solde: $soldeStr, rfid: $rfid")

        // Parse the solde string to a float value with error handling
        var soldeValue = 0.00f
        try {
            // Clean and parse the solde value
            val cleanSolde = soldeStr.replace(Regex("[^\\d.]"), "")
            soldeValue = cleanSolde.toFloatOrNull() ?: 0.00f
        } catch (e: Exception) {
            Log.e(TAG, "Error parsing solde: ${e.message}")
        }

        // Format the RFID number with spaces for display
        val formattedRfid = if (rfid.isNotEmpty()) {
            formatRfidWithSpaces(rfid)
        } else {
            // If no RFID is found, show a placeholder
            "RFID Not Available"
        }

        // Set values to UI
        balanceText.text = String.format("%.2f MAD", soldeValue)
        cardNumberText.text = formattedRfid
        cardNameTextView.text = name
        expiryDateText.text = "N/A" // RFID doesn't typically have expiry

        Log.d(TAG, "UI updated with - name: $name, solde: $soldeValue, formatted RFID: $formattedRfid")
    }

    /**
     * Formats an RFID string by adding spaces every 4 characters
     */
    private fun formatRfidWithSpaces(rfid: String): String {
        val cleaned = rfid.replace(" ", "") // Remove any existing spaces
        val result = StringBuilder()

        // Add a space after every 4 characters
        for (i in cleaned.indices) {
            result.append(cleaned[i])
            if ((i + 1) % 4 == 0 && i < cleaned.length - 1) {
                result.append(" ")
            }
        }

        return result.toString()
    }

    // Rest of the code remains the same...

    private fun setupActionCards() {
        // Scan action
        scanAction.findViewById<ImageView>(R.id.actionIcon).setImageResource(R.drawable.ic_scan)
        scanAction.findViewById<TextView>(R.id.actionLabel).text = "Scan\nCode"
        scanAction.setOnClickListener {
            startActivity(Intent(this, QrActivity::class.java))
        }

        // History action
        historyAction.findViewById<ImageView>(R.id.actionIcon).setImageResource(R.drawable.ic_history)
        historyAction.findViewById<TextView>(R.id.actionLabel).text = "Purchase\nHistory"
        historyAction.setOnClickListener {
            startActivity(Intent(this, HistoryActivity::class.java))
        }

        // Products action
        productsAction.findViewById<ImageView>(R.id.actionIcon).setImageResource(R.drawable.ic_products)
        productsAction.findViewById<TextView>(R.id.actionLabel).text = "Browse\nProducts"
        productsAction.setOnClickListener {
            // Launch products activity
            startActivity(Intent(this, ProductsActivity::class.java))
        }

        // Profile action
        profileAction.findViewById<ImageView>(R.id.actionIcon).setImageResource(R.drawable.ic_profile)
        profileAction.findViewById<TextView>(R.id.actionLabel).text = "My\nProfile"
        profileAction.setOnClickListener {
            startActivity(Intent(this, ProfileActivity::class.java))
        }
    }

    private fun setupRecentPurchases() {
        // Set up recycler view
        recentPurchasesRecyclerView.layoutManager = LinearLayoutManager(this)

        // Load recent purchases
        loadRecentPurchases()

        // Set adapter
        val adapter = RecentPurchaseAdapter(this, recentPurchases)
        recentPurchasesRecyclerView.adapter = adapter

        // Show empty state if no purchases
        if (recentPurchases.isEmpty()) {
            emptyHistoryText.visibility = View.VISIBLE
            recentPurchasesRecyclerView.visibility = View.GONE
        } else {
            emptyHistoryText.visibility = View.GONE
            recentPurchasesRecyclerView.visibility = View.VISIBLE
        }
    }

    private fun loadRecentPurchases() {
        // Clear existing data
        recentPurchases.clear()

        // Demo data for recent purchases
        val currentDate = Date()
        val calendar = java.util.Calendar.getInstance()

        try {
            // Most recent purchase
            recentPurchases.add(
                PurchaseItem(
                    id = 101,
                    date = Date(currentDate.time - 2 * 60 * 60 * 1000), // 2 hours ago
                    amount = 120.50,
                    productsList = "Milk, Bread, Eggs, Rice"
                )
            )

            // Second recent purchase
            calendar.time = currentDate
            calendar.add(java.util.Calendar.DAY_OF_MONTH, -2) // 2 days ago
            recentPurchases.add(
                PurchaseItem(
                    id = 100,
                    date = calendar.time,
                    amount = 85.25,
                    productsList = "Apples, Bananas, Oranges"
                )
            )

            // Third recent purchase
            calendar.time = currentDate
            calendar.add(java.util.Calendar.DAY_OF_MONTH, -5) // 5 days ago
            recentPurchases.add(
                PurchaseItem(
                    id = 99,
                    date = calendar.time,
                    amount = 210.00,
                    productsList = "Chicken, Beef, Vegetables"
                )
            )
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up demo purchases: ${e.message}")
        }
    }

    private fun copyRfidToClipboard() {
        val clipboard = getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        val clip = ClipData.newPlainText("RFID Number", cardNumberText.text.toString())
        clipboard.setPrimaryClip(clip)
        Toast.makeText(this, "RFID number copied to clipboard", Toast.LENGTH_SHORT).show()
    }

    override fun onResume() {
        super.onResume()
        // Refresh user data every time the activity comes to foreground
        setupCustomerInfo()
    }
}