package com.example.smartgrocery

import android.content.Intent
import android.os.Bundle
import android.view.MenuItem
import androidx.appcompat.app.AppCompatActivity
import com.example.smartgrocery.databinding.ActivityProfileBinding

class ProfileActivity : AppCompatActivity() {

    private lateinit var binding: ActivityProfileBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityProfileBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Set up toolbar
        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "My Profile"

        // Set up bottom navigation
            //setupBottomNavigation()

        // Load user data
        loadUserProfile()
    }

//    private fun setupBottomNavigation() {
//        binding.bottomNavigation.selectedItemId = R.id.menu_profile
//        binding.bottomNavigation.setOnItemSelectedListener { item ->
//            when (item.itemId) {
//                R.id.menu_home -> {
//                    startActivity(Intent(this, MainActivity::class.java))
//                    true
//                }
//                R.id.menu_history -> {
//                    startActivity(Intent(this, HistoryActivity::class.java))
//                    true
//                }
//                R.id.menu_cart -> {
//                    startActivity(Intent(this, QRActivity::class.java))
//                    true
//                }
//                R.id.menu_profile -> {
//                    // Already on this activity
//                    true
//                }
//                else -> false
//            }
//        }
//    }

    private fun loadUserProfile() {
        // Get user data from SharedPreferences
        val sharedPrefs = getSharedPreferences("UserData", MODE_PRIVATE)
        val userName = sharedPrefs.getString("name", "Guest User") ?: "Guest User"
        val userEmail = sharedPrefs.getString("email", "") ?: ""

        // Display user info
        binding.userNameTextView.text = userName
        binding.userEmailTextView.text = userEmail
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (item.itemId == android.R.id.home) {
            finish()
            return true
        }
        return super.onOptionsItemSelected(item)
    }
}