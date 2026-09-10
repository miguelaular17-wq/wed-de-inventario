package com.example.inventario

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.inventario.ui.AppViewModel
import com.example.inventario.ui.InventarioApp
import com.example.inventario.ui.theme.InventarioTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val container = AppContainer(applicationContext)
        setContent {
            InventarioTheme {
                val appViewModel: AppViewModel = viewModel(
                    factory = AppViewModel.Factory(container.repository),
                )
                InventarioApp(appViewModel)
            }
        }
    }
}