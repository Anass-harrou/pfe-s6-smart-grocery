package com.example.smartgrocery

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.MenuItem
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.example.smartgrocery.databinding.ActivityPaymentBinding
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import android.content.Intent

class PaymentActivity : AppCompatActivity() {

    private lateinit var binding: ActivityPaymentBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityPaymentBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Set up toolbar
        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Payment"

        // Get data from intent
        val paymentInfo = intent.getStringExtra("PAYMENT_INFO") ?: ""
        val amount = intent.getStringExtra("AMOUNT") ?: "0.00"

        // Display amount
        binding.amountTextView.text = "$amount DH"

        // Set up pay button
        binding.payButton.setOnClickListener {
            processPayment(amount)
        }

        // Log activity access
        val timestamp = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
            .format(Date())
        println("Payment activity opened by Anass-harrou at $timestamp")
    }

    private fun processPayment(amount: String) {
        // Show progress
        binding.progressBar.visibility = View.VISIBLE
        binding.payButton.isEnabled = false

        // Simulate payment processing
        Handler(Looper.getMainLooper()).postDelayed({
            binding.progressBar.visibility = View.GONE

            // Show success message
            Toast.makeText(
                this,
                "Payment of $amount DH successful!",
                Toast.LENGTH_LONG
            ).show()

            // Navigate to receipt
            val paymentMethod = when {
                binding.radioCreditCard.isChecked -> "Credit Card"
                binding.radioPaypal.isChecked -> "PayPal"
                else -> "Bank Transfer"
            }

            // Generate transaction ID
            val transactionId = System.currentTimeMillis() % 10000

            val intent = Intent(this, ReceiptActivity::class.java).apply {
                putExtra("TRANSACTION_ID", transactionId)
                putExtra("TRANSACTION_TITLE", "Store Purchase")
                putExtra("TRANSACTION_SUBTITLE", "Payment via $paymentMethod")
                putExtra("TRANSACTION_AMOUNT", amount.toDoubleOrNull() ?: 0.0)
                putExtra("TRANSACTION_TYPE", "debit")
                putExtra("TRANSACTION_DATE", SimpleDateFormat(
                    "yyyy-MM-dd HH:mm:ss",
                    Locale.getDefault()
                ).format(Date()))
            }

            startActivity(intent)
            finish()
        }, 2000) // 2 second delay to simulate processing
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (item.itemId == android.R.id.home) {
            finish()
            return true
        }
        return super.onOptionsItemSelected(item)
    }
}