package com.example.smartgrocery

import android.content.Intent
import android.os.Bundle
import android.text.TextUtils
import android.util.Log
import android.util.Patterns
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.android.volley.Response
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.smartgrocery.databinding.ActivityLoginBinding
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Enhanced Material Design Login Activity for Smart Grocery App
 * Date: 2025-06-23 02:04:30
 * Author: Anass-harrou
 */
class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private val TAG = "LoginActivity"

    // API URL - Make sure this matches your server address
    private val API_URL = "http://192.168.1.10/APIPHP2/login.php"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Set status bar color
        window.statusBarColor = ContextCompat.getColor(this, R.color.primary_color)

        // Update timestamp for development (remove in production)
        val currentDateTime = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())
        binding.timestampText.text = "$currentDateTime | Anass-harrou"

        // Set up login button click listener
        binding.loginButton.setOnClickListener {
            val email = binding.emailInput.text.toString().trim()
            val password = binding.passwordInput.text.toString().trim()

            if (validateInputs(email, password)) {
                login(email, password)
            }
        }

        // Set up forgot password click listener
        binding.forgotPasswordText.setOnClickListener {
            // Show simple toast for now, replace with actual recovery flow
            Toast.makeText(this, "Password recovery coming soon!", Toast.LENGTH_SHORT).show()
        }

        // Set up sign up text click listener
        binding.signUpText.setOnClickListener {
            // Navigate to sign up (implement later)
            Toast.makeText(this, "Registration coming soon!", Toast.LENGTH_SHORT).show()
            // startActivity(Intent(this, RegisterActivity::class.java))
        }

        // Social login buttons
        binding.googleLoginButton.setOnClickListener {
            Toast.makeText(this, "Google login coming soon!", Toast.LENGTH_SHORT).show()
            // Add Google sign-in implementation
        }

        binding.facebookLoginButton.setOnClickListener {
            Toast.makeText(this, "Facebook login coming soon!", Toast.LENGTH_SHORT).show()
            // Add Facebook sign-in implementation
        }
    }

    private fun validateInputs(email: String, password: String): Boolean {
        // Clear previous errors
        binding.emailLayout.error = null
        binding.passwordLayout.error = null

        // Validate email
        if (email.isEmpty()) {
            binding.emailLayout.error = "Email is required"
            binding.emailInput.requestFocus()
            return false
        } else if (!isValidEmail(email)) {
            binding.emailLayout.error = "Enter a valid email address"
            binding.emailInput.requestFocus()
            return false
        }

        // Validate password
        if (password.isEmpty()) {
            binding.passwordLayout.error = "Password is required"
            binding.passwordInput.requestFocus()
            return false
        } else if (password.length < 6) {
            binding.passwordLayout.error = "Password must be at least 6 characters"
            binding.passwordInput.requestFocus()
            return false
        }

        return true
    }

    private fun isValidEmail(email: String): Boolean {
        return !TextUtils.isEmpty(email) && Patterns.EMAIL_ADDRESS.matcher(email).matches()
    }

    private fun login(email: String, password: String) {
        showLoading(true)

        // Use StringRequest with POST parameters
        val request = object : StringRequest(
            Method.POST,
            API_URL,
            Response.Listener { response ->
                Log.d(TAG, "Server response: $response")
                showLoading(false)

                try {
                    val jsonResponse = JSONObject(response)
                    processLoginResponse(jsonResponse)
                } catch (e: Exception) {
                    Log.e(TAG, "Error parsing JSON: ${e.message}")
                    showError("Server error. Please try again.")
                }
            },
            Response.ErrorListener { error ->
                showLoading(false)
                Log.e(TAG, "Error: ${error.message}")
                showError("Connection error. Please check your internet connection.")
            }
        ) {
            // Override getParams to add POST parameters
            override fun getParams(): MutableMap<String, String> {
                val params = HashMap<String, String>()
                params["email"] = email
                params["password"] = password
                return params
            }
        }

        // Add request to queue
        Volley.newRequestQueue(this).add(request)
    }

    private fun processLoginResponse(response: JSONObject) {
        val success = response.optBoolean("success", false)

        if (success) {
            // Login successful
            val userData = response.getJSONObject("user")
            saveUserData(userData)
            navigateToMainActivity()
        } else {
            // Login failed
            val message = response.optString("message", "Login failed")
            Toast.makeText(this, message, Toast.LENGTH_LONG).show()
        }
    }

    private fun saveUserData(userData: JSONObject) {
        val prefs = getSharedPreferences("user_data", MODE_PRIVATE)
        val editor = prefs.edit()

        // Store user data as strings to avoid type mismatches
        editor.putString("id", userData.optString("id"))
        editor.putString("name", userData.optString("name"))
        editor.putString("email", userData.optString("email"))
        editor.putString("solde", userData.optString("solde"))
        editor.putString("last_login", userData.optString("last_login"))

        // Save the RFID value from the database
        editor.putString("rfid", userData.optString("rfid"))

        // Log for debugging
        Log.d(TAG, "Saving user data: id=${userData.optString("id")}, " +
                "name=${userData.optString("name")}, " +
                "solde=${userData.optString("solde")}, " +
                "rfid=${userData.optString("rfid")}")

        editor.apply()
    }

    private fun navigateToMainActivity() {
        val intent = Intent(this, MainActivity::class.java)
        startActivity(intent)
        finish() // Close login activity
    }

    private fun showLoading(isLoading: Boolean) {
        binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        binding.loginButton.isEnabled = !isLoading
        binding.emailInput.isEnabled = !isLoading
        binding.passwordInput.isEnabled = !isLoading
        binding.googleLoginButton.isEnabled = !isLoading
        binding.facebookLoginButton.isEnabled = !isLoading
        binding.forgotPasswordText.isEnabled = !isLoading
        binding.signUpText.isEnabled = !isLoading

        // Fade out the card when loading
        binding.loginCard.alpha = if (isLoading) 0.6f else 1.0f
    }

    private fun showError(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    }
}