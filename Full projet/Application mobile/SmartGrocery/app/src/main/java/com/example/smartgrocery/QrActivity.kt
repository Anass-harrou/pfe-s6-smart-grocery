package com.example.smartgrocery

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.util.Size
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.android.volley.Response
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.smartgrocery.databinding.ActivityQrBinding
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import org.json.JSONObject
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Smart Cart App - QR Code Scanner Activity
 * Date: 2025-06-20 22:28:51
 * Author: Anass-harrou
 */
class QrActivity : AppCompatActivity() {

    private lateinit var binding: ActivityQrBinding
    private var cameraExecutor: ExecutorService = Executors.newSingleThreadExecutor()
    private var scanMode = SCAN_MODE_NONE
    private var isScannerActive = false

    private val TAG = "QrActivity"
    private val CAMERA_REQUEST_CODE = 101

    // API URLs - Use your actual server URLs
    private val PAYMENT_API_URL = "http://192.168.1.10/APIPHP2/process_payment.php"
    private val LOGIN_API_URL = "http://192.168.1.10/APIPHP2/qr_login.php"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityQrBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Set up toolbar
        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "QR Scanner"

        // Check if we're launching into a specific mode
        val intentMode = intent.getIntExtra(EXTRA_SCAN_MODE, SCAN_MODE_NONE)
        if (intentMode != SCAN_MODE_NONE) {
            scanMode = intentMode
            checkPermissionsAndStartCamera()
        }

        // Set up bottom navigation
        binding.bottomNavigation.setOnItemSelectedListener { menuItem ->
            when (menuItem.itemId) {
                R.id.nav_home -> {
                    startActivity(Intent(this, MainActivity::class.java))
                    finish()
                    true
                }
                R.id.nav_history -> {
                    startActivity(Intent(this, HistoryActivity::class.java))
                    finish()
                    true
                }
                else -> false
            }
        }

        // Setup buttons - simplified to directly launch camera
        binding.paymentButton.setOnClickListener {
            scanMode = SCAN_MODE_PAYMENT
            checkPermissionsAndStartCamera()
        }

        binding.loginButton.setOnClickListener {
            scanMode = SCAN_MODE_LOGIN
            checkPermissionsAndStartCamera()
        }

        // Set up floating action button
        binding.qr.setOnClickListener {
            if (scanMode == SCAN_MODE_NONE) {
                // If no mode is selected, show buttons
                if (binding.buttonsContainer.visibility == View.VISIBLE) {
                    binding.buttonsContainer.visibility = View.GONE
                } else {
                    binding.buttonsContainer.visibility = View.VISIBLE
                }
            } else {
                // If already in scanning mode, restart scanner
                startCamera()
            }
        }
    }

    private fun checkPermissionsAndStartCamera() {
        // Hide the buttons once a mode is selected
        binding.buttonsContainer.visibility = View.GONE

        // Update UI for selected mode
        when (scanMode) {
            SCAN_MODE_PAYMENT -> {
                binding.scanInstructions.text = "Scan payment QR code"
            }
            SCAN_MODE_LOGIN -> {
                binding.scanInstructions.text = "Scan login QR code"
            }
        }

        // Show the camera container
        binding.cameraContainer.visibility = View.VISIBLE

        // Check camera permission
        if (ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED
        ) {
            startCamera()
        } else {
            ActivityCompat.requestPermissions(
                this,
                arrayOf(Manifest.permission.CAMERA),
                CAMERA_REQUEST_CODE
            )
        }
    }

    private fun startCamera() {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(this)

        cameraProviderFuture.addListener({
            try {
                val cameraProvider = cameraProviderFuture.get()

                // Set up the preview use case
                val preview = Preview.Builder()
                    .build()
                    .also {
                        it.setSurfaceProvider(binding.previewView.surfaceProvider)
                    }

                // Set up the image analysis use case
                val imageAnalyzer = ImageAnalysis.Builder()
                    .setTargetResolution(Size(1280, 720))
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                    .build()
                    .also {
                        it.setAnalyzer(cameraExecutor, QrCodeAnalyzer { barcodes ->
                            if (barcodes.isNotEmpty() && isScannerActive) {
                                isScannerActive = false // Prevent duplicate scans
                                barcodes[0].rawValue?.let { code ->
                                    runOnUiThread {
                                        binding.scanningStatus.text = "Processing..."
                                        binding.scanningStatus.visibility = View.VISIBLE
                                        processQrCode(code)
                                    }
                                }
                            }
                        })
                    }

                // Select back camera
                val cameraSelector = CameraSelector.DEFAULT_BACK_CAMERA

                // Unbind any bound use cases before rebinding
                cameraProvider.unbindAll()

                // Bind use cases to camera
                cameraProvider.bindToLifecycle(
                    this,
                    cameraSelector,
                    preview,
                    imageAnalyzer
                )

                // Mark scanner as active
                isScannerActive = true

            } catch (exc: Exception) {
                Log.e(TAG, "Use case binding failed", exc)
                Toast.makeText(
                    this,
                    "Failed to start camera: ${exc.message}",
                    Toast.LENGTH_SHORT
                ).show()
            }
        }, ContextCompat.getMainExecutor(this))
    }

    private fun processQrCode(qrCode: String) {
        Log.d(TAG, "Raw QR code data: $qrCode")

        when (scanMode) {
            SCAN_MODE_PAYMENT -> processPaymentQrCode(qrCode)
            SCAN_MODE_LOGIN -> processLoginQrCode(qrCode)
            else -> {
                // Auto-detect QR type based on content
                if (qrCode.contains("\"type\":\\s*\"login\"") ||
                    qrCode.contains("qr_login_token")) {
                    scanMode = SCAN_MODE_LOGIN
                    processLoginQrCode(qrCode)
                } else if (qrCode.contains("transaction_id") ||
                    qrCode.contains("amount")) {
                    scanMode = SCAN_MODE_PAYMENT
                    processPaymentQrCode(qrCode)
                } else {
                    binding.scanningStatus.text = "Unrecognized QR code format"
                    Handler(Looper.getMainLooper()).postDelayed({
                        binding.scanningStatus.visibility = View.GONE
                        isScannerActive = true
                    }, 2000)
                }
            }
        }
    }

    private fun processPaymentQrCode(qrCode: String) {
        // Get user ID from preferences
        val userId = getUserId()

        // Extract payment information from QR code
        val paymentInfo = extractPaymentDataFromQrCode(qrCode)

        if (paymentInfo.isEmpty()) {
            binding.scanningStatus.text = "Invalid payment QR code format."
            Handler(Looper.getMainLooper()).postDelayed({
                binding.scanningStatus.visibility = View.GONE
                isScannerActive = true
            }, 3000)
            return
        }

        val transactionId = paymentInfo["transaction_id"] ?: ""
        val amount = paymentInfo["amount"]?.toFloatOrNull() ?: 0f
        val clientId = paymentInfo["user_id"] ?: userId

        if (transactionId.isEmpty() || amount <= 0) {
            binding.scanningStatus.text = "Invalid payment data in QR code."
            Handler(Looper.getMainLooper()).postDelayed({
                binding.scanningStatus.visibility = View.GONE
                isScannerActive = true
            }, 3000)
            return
        }

        Log.d(TAG, "Processing payment: transaction=$transactionId, amount=$amount")
        binding.scanningStatus.text = "Processing payment of $amount dh..."

        // Create JSON request body
        val requestBody = JSONObject().apply {
            put("user_id", userId)
            put("transaction_id", transactionId)
            put("amount", amount)
        }

        // Send POST request with JSON body using Volley
        val request = object : StringRequest(
            Method.POST,
            PAYMENT_API_URL,
            Response.Listener { response ->
                Log.d(TAG, "Payment response: $response")
                try {
                    val jsonResponse = JSONObject(response)
                    val success = jsonResponse.optBoolean("success", false)
                    val message = jsonResponse.optString("message", "Unknown error")

                    if (success) {
                        // Payment successful
                        val newBalance = jsonResponse.optString("new_balance", "0.00")

                        // Update stored balance
                        updateStoredBalance(newBalance)

                        // Show success animation
                        showSuccessAnimation("Payment successful!\nAmount: $amount dh\nNew balance: $newBalance dh")
                    } else {
                        // Payment failed
                        binding.scanningStatus.text = "Payment failed: $message"
                        Handler(Looper.getMainLooper()).postDelayed({
                            binding.scanningStatus.visibility = View.GONE
                            isScannerActive = true
                        }, 3000)
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error parsing payment response", e)
                    binding.scanningStatus.text = "Error processing payment"
                    Handler(Looper.getMainLooper()).postDelayed({
                        binding.scanningStatus.visibility = View.GONE
                        isScannerActive = true
                    }, 3000)
                }
            },
            Response.ErrorListener { error ->
                Log.e(TAG, "Payment error", error)
                binding.scanningStatus.text = "Connection error"
                Handler(Looper.getMainLooper()).postDelayed({
                    binding.scanningStatus.visibility = View.GONE
                    isScannerActive = true
                }, 3000)
            }
        ) {
            override fun getBodyContentType(): String {
                return "application/json; charset=utf-8"
            }

            override fun getBody(): ByteArray {
                return requestBody.toString().toByteArray()
            }
        }

        Volley.newRequestQueue(this).add(request)
    }

    private fun processLoginQrCode(qrCode: String) {
        // Implementation of QR login processing
        // Get user ID from preferences
        val userId = getUserId()

        // Extract token from QR code
        val token = extractTokenFromQrCode(qrCode)

        if (token.isEmpty()) {
            binding.scanningStatus.text = "Invalid QR code format. No token found."
            Handler(Looper.getMainLooper()).postDelayed({
                binding.scanningStatus.visibility = View.GONE
                isScannerActive = true
            }, 3000)
            return
        }

        Log.d(TAG, "Extracted login token: $token")

        // Prepare request
        val request = object : StringRequest(
            Method.POST,
            LOGIN_API_URL,
            Response.Listener { response ->
                Log.d(TAG, "Login response: $response")
                try {
                    val jsonResponse = JSONObject(response)
                    val success = jsonResponse.optBoolean("success", false)
                    val message = jsonResponse.optString("message", "Unknown error")

                    if (success) {
                        // Login successful
                        showSuccessAnimation("Login successful!")
                    } else {
                        // Login failed
                        binding.scanningStatus.text = "Login failed: $message"
                        Handler(Looper.getMainLooper()).postDelayed({
                            binding.scanningStatus.visibility = View.GONE
                            isScannerActive = true
                        }, 3000)
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error parsing login response", e)
                    binding.scanningStatus.text = "Error processing login"
                    Handler(Looper.getMainLooper()).postDelayed({
                        binding.scanningStatus.visibility = View.GONE
                        isScannerActive = true
                    }, 3000)
                }
            },
            Response.ErrorListener { error ->
                Log.e(TAG, "Login error", error)
                binding.scanningStatus.text = "Connection error"
                Handler(Looper.getMainLooper()).postDelayed({
                    binding.scanningStatus.visibility = View.GONE
                    isScannerActive = true
                }, 3000)
            }
        ) {
            override fun getParams(): MutableMap<String, String> {
                val params = HashMap<String, String>()
                params["user_id"] = userId
                params["qr_login_token"] = token
                return params
            }
        }

        Volley.newRequestQueue(this).add(request)
    }

    private fun extractPaymentDataFromQrCode(qrCode: String): Map<String, String> {
        val result = mutableMapOf<String, String>()

        try {
            // Try to parse as JSON
            try {
                val jsonObject = JSONObject(qrCode)
                if (jsonObject.has("transaction_id")) {
                    result["transaction_id"] = jsonObject.getString("transaction_id")
                }
                if (jsonObject.has("amount")) {
                    result["amount"] = jsonObject.getString("amount")
                }
                if (jsonObject.has("user_id")) {
                    result["user_id"] = jsonObject.getString("user_id")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error parsing QR JSON", e)
            }

            // If JSON parsing failed or values weren't found, try URL format
            if (result.isEmpty() && qrCode.contains("?")) {
                val queryString = qrCode.substring(qrCode.indexOf('?') + 1)
                val queryParams = queryString.split("&")
                for (param in queryParams) {
                    val parts = param.split("=")
                    if (parts.size == 2) {
                        when (parts[0]) {
                            "transaction_id", "transactionId" -> result["transaction_id"] = parts[1]
                            "amount" -> result["amount"] = parts[1]
                            "user_id", "userId", "client_id" -> result["user_id"] = parts[1]
                        }
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error extracting payment data", e)
        }

        return result
    }

    private fun extractTokenFromQrCode(qrCode: String): String {
        Log.d(TAG, "Attempting to extract token from: $qrCode")

        try {
            // Handle JSON format
            if (qrCode.startsWith("{") && qrCode.endsWith("}")) {
                try {
                    val jsonObject = JSONObject(qrCode)

                    // Check for token in standard fields
                    if (jsonObject.has("qr_login_token")) {
                        return jsonObject.getString("qr_login_token")
                    } else if (jsonObject.has("token")) {
                        return jsonObject.getString("token")
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "JSON parsing error", e)
                }
            }

            // Check if it's a URL with query parameters
            if (qrCode.contains("?") && qrCode.contains("=")) {
                val queryString = qrCode.substring(qrCode.indexOf('?') + 1)
                val queryParams = queryString.split("&")
                for (param in queryParams) {
                    val parts = param.split("=")
                    if (parts.size == 2) {
                        if (parts[0] == "token" || parts[0] == "qr_login_token") {
                            return parts[1]
                        }
                    }
                }
            }

            // If the QR code itself is a simple string token
            if (!qrCode.contains("{") && !qrCode.contains("=") &&
                !qrCode.contains(" ") && qrCode.length >= 8) {
                return qrCode.trim()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error extracting token", e)
        }

        return ""
    }

    private fun updateStoredBalance(newBalance: String) {
        val prefs = getSharedPreferences("user_data", MODE_PRIVATE)
        prefs.edit().putString("solde", newBalance).apply()
    }

    private fun getUserId(): String {
        val prefs = getSharedPreferences("user_data", MODE_PRIVATE)
        return prefs.getString("id", "0") ?: "0"
    }

    private fun showSuccessAnimation(message: String) {
        // Hide scanner and show success view
        binding.cameraContainer.visibility = View.INVISIBLE
        binding.successView.visibility = View.VISIBLE
        binding.successMessage.text = message

        // Auto-return to main screen after delay
        Handler(Looper.getMainLooper()).postDelayed({
            val intent = Intent(this, MainActivity::class.java)
            startActivity(intent)
            finish()
        }, 3000)
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == CAMERA_REQUEST_CODE) {
            if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                startCamera()
            } else {
                Toast.makeText(
                    this,
                    "Camera permission is required to scan QR codes",
                    Toast.LENGTH_LONG
                ).show()
                scanMode = SCAN_MODE_NONE
                binding.cameraContainer.visibility = View.GONE
                binding.buttonsContainer.visibility = View.VISIBLE
            }
        }
    }

    override fun onResume() {
        super.onResume()
        if (scanMode != SCAN_MODE_NONE &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) ==
            PackageManager.PERMISSION_GRANTED) {
            isScannerActive = true
        }
    }

    override fun onPause() {
        isScannerActive = false
        super.onPause()
    }

    override fun onDestroy() {
        cameraExecutor.shutdown()
        super.onDestroy()
    }

    companion object {
        const val EXTRA_SCAN_MODE = "extra_scan_mode"
        const val SCAN_MODE_NONE = 0
        const val SCAN_MODE_PAYMENT = 1
        const val SCAN_MODE_LOGIN = 2
    }

    // QR Code Analyzer class
    private class QrCodeAnalyzer(private val onQrCodesDetected: (qrCodes: List<Barcode>) -> Unit) : ImageAnalysis.Analyzer {
        private val scanner = BarcodeScanning.getClient()

        @androidx.camera.core.ExperimentalGetImage
        override fun analyze(imageProxy: ImageProxy) {
            val mediaImage = imageProxy.image
            if (mediaImage != null) {
                val image = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)

                scanner.process(image)
                    .addOnSuccessListener { barcodes ->
                        if (barcodes.isNotEmpty()) {
                            onQrCodesDetected(barcodes)
                        }
                    }
                    .addOnCompleteListener {
                        imageProxy.close()
                    }
            } else {
                imageProxy.close()
            }
        }
    }
}