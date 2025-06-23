package com.example.smartgrocery.adapter

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.example.smartgrocery.R
import com.example.smartgrocery.databinding.ItemTransactionBinding
import com.example.smartgrocery.model.Transaction
import java.text.SimpleDateFormat
import java.util.*

class TransactionAdapter(private val onItemClick: (Transaction) -> Unit) :
    ListAdapter<Transaction, TransactionAdapter.TransactionViewHolder>(DIFF_CALLBACK) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): TransactionViewHolder {
        val binding = ItemTransactionBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return TransactionViewHolder(binding)
    }

    override fun onBindViewHolder(holder: TransactionViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class TransactionViewHolder(private val binding: ItemTransactionBinding) :
        RecyclerView.ViewHolder(binding.root) {

        init {
            binding.root.setOnClickListener {
                val position = adapterPosition
                if (position != RecyclerView.NO_POSITION) {
                    onItemClick(getItem(position))
                }
            }
        }

        fun bind(transaction: Transaction) {
            binding.apply {
                // Set transaction title
                tvTransactionTitle.text = transaction.title

                // Set transaction subtitle
                tvTransactionSubtitle.text = transaction.subtitle

                // Format and set date
                val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                val outputFormat = SimpleDateFormat("MMM dd, yyyy", Locale.getDefault())
                try {
                    val date = inputFormat.parse(transaction.date)
                    tvTransactionDate.text = date?.let { outputFormat.format(it) } ?: transaction.date
                } catch (e: Exception) {
                    // If date parsing fails, show original date string
                    tvTransactionDate.text = transaction.date
                }

                // Set amount with sign based on transaction type
                val amountText = when (transaction.type.lowercase()) {
                    "credit" -> "+${String.format(Locale.getDefault(), "%.2f", transaction.amount)} DH"
                    else -> "-${String.format(Locale.getDefault(), "%.2f", transaction.amount)} DH"
                }
                tvTransactionAmount.text = amountText

                // Set amount color based on transaction type
                val colorRes = when (transaction.type.lowercase()) {
                    "credit" -> android.R.color.holo_green_dark
                    else -> android.R.color.holo_red_dark
                }
                tvTransactionAmount.setTextColor(root.context.getColor(colorRes))

                // Set icon based on transaction source
                val iconRes = when {
                    transaction.source == "achats" ||
                            transaction.title.contains("Purchase", ignoreCase = true) ->
                        R.drawable.ic_shopping_cart
                    transaction.type.equals("credit", ignoreCase = true) ->
                        R.drawable.ic_money_in
                    else ->
                        R.drawable.ic_money_out
                }
                ivTransactionIcon.setImageResource(iconRes)
            }
        }
    }

    companion object {
        private val DIFF_CALLBACK = object : DiffUtil.ItemCallback<Transaction>() {
            override fun areItemsTheSame(oldItem: Transaction, newItem: Transaction): Boolean {
                return oldItem.id == newItem.id && oldItem.source == newItem.source
            }

            override fun areContentsTheSame(oldItem: Transaction, newItem: Transaction): Boolean {
                return oldItem == newItem
            }
        }
    }
}