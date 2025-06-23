package com.example.smartgrocery

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.view.View
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.widget.Toolbar
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.android.volley.Request
import com.android.volley.toolbox.JsonArrayRequest
import com.android.volley.toolbox.Volley
import com.example.smartgrocery.adapters.PurchaseHistoryAdapter
import java.text.SimpleDateFormat
import java.util.*
import kotlin.collections.ArrayList

class HistoryActivity : AppCompatActivity() {

    private lateinit var recyclerView: RecyclerView
    private lateinit var progressBar: ProgressBar
    private lateinit var emptyView: TextView
    private lateinit var toolbar: Toolbar
    private val purchases = ArrayList<PurchaseItem>()
    private lateinit var adapter: PurchaseHistoryAdapter

    // Storage permission request
    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        if (isGranted) {
            Log.d(TAG, "Storage permission granted")
        } else {
            Toast.makeText(
                this,
                "Storage permission denied. You won't be able to download receipts.",
                Toast.LENGTH_LONG
            ).show()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_history)

        // Initialize UI components
        toolbar = findViewById(R.id.toolbar)
        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Purchase History"

        recyclerView = findViewById(R.id.purchase_history_recycler_view)
        progressBar = findViewById(R.id.progress_bar)
        emptyView = findViewById(R.id.empty_view)

        // Set up RecyclerView
        recyclerView.layoutManager = LinearLayoutManager(this)
        adapter = PurchaseHistoryAdapter(this, purchases)
        recyclerView.adapter = adapter

        // Check for storage permission
        checkStoragePermission()

        // Load purchase history
        loadPurchaseHistory()
    }

    private fun checkStoragePermission() {
        if (Build.VERSION.SDK_INT <= Build.VERSION_CODES.P) {
            val permission = Manifest.permission.WRITE_EXTERNAL_STORAGE
            when {
                ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED -> {
                    // Permission already granted
                }
                shouldShowRequestPermissionRationale(permission) -> {
                    // Show explanation
                    Toast.makeText(
                        this,
                        "Storage permission is needed to download receipts",
                        Toast.LENGTH_LONG
                    ).show()
                    requestPermissionLauncher.launch(permission)
                }
                else -> {
                    // Request permission
                    requestPermissionLauncher.launch(permission)
                }
            }
        }
    }


    private fun loadPurchaseHistory() {
        showLoading(true)

        // Get user ID from preferences
        val prefs = getSharedPreferences("user_data", MODE_PRIVATE)
        val userId = prefs.getString("id", "0") ?: "0"

        // API endpoint
        val url = getString(R.string.api_base_url) + "/get_purchase_history.php?user_id=$userId"

        val request = JsonArrayRequest(
            Request.Method.GET, url, null,
            { response ->
                purchases.clear()

                // Parse the response
                for (i in 0 until response.length()) {
                    try {
                        val purchase = response.getJSONObject(i)
                        val id = purchase.getInt("id_achat")
                        val dateStr = purchase.getString("date_achat")
                        val amount = purchase.getDouble("montant_total")
                        val productsList = purchase.getString("products")

                        // Parse the date
                        val date = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                            .parse(dateStr) ?: Date()

                        // Add to the list
                        purchases.add(
                            PurchaseItem(
                                id = id,
                                date = date,
                                amount = amount,
                                productsList = productsList
                            )
                        )
                    } catch (e: Exception) {
                        Log.e(TAG, "Error parsing purchase data", e)
                    }
                }

                // Update UI
                showLoading(false)
                updateUI()
            },
            { error ->
                Log.e(TAG, "Error fetching purchase history", error)
                showLoading(false)
                showError("Failed to load purchase history: ${error.message}")
            }
        )

        // Add request to queue
        Volley.newRequestQueue(this).add(request)
    }
    private fun showLoading(isLoading: Boolean) {
        progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        recyclerView.visibility = View.GONE
        emptyView.visibility = View.GONE
    }

    private fun updateUI() {
        if (purchases.isEmpty()) {
            recyclerView.visibility = View.GONE
            emptyView.visibility = View.VISIBLE
        } else {
            recyclerView.visibility = View.VISIBLE
            emptyView.visibility = View.GONE
            adapter.notifyDataSetChanged()
        }
    }

    private fun showError(message: String) {
        recyclerView.visibility = View.GONE
        emptyView.visibility = View.VISIBLE
        emptyView.text = message

        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }

    companion object {
        private const val TAG = "HistoryActivity"
    }
}